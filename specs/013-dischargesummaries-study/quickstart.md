# Quickstart: Run `dischargesummaries` Beside `scans` on One VM

**Feature**: 013-dischargesummaries-study

Applies to the dev VM (socket mode) and the prod VM (tcp mode, sharing the
`scans` SSH tunnel). The `scans` deployment is not stopped or changed at any
step.

> **Prod gate**: until Phase B (the review form) is merged, use prod only
> for upload and scrub. Reviewers will see "review form not available yet".

## 1. Separate checkout

```bash
sudo -u <deploy-user> git clone <repo> /srv/cnics-dischargesummaries
cd /srv/cnics-dischargesummaries
cp default.env .env
```

## 2. `.env` — values that must differ from the `scans` deployment

```ini
COMPOSE_PROJECT_NAME=cnics-dischargesummaries
EXTERNAL_PORT=3010            # any free port; scans uses its own
BACKEND_EXTERNAL_PORT=3011
SERVER_NAME=dischargesummaries.<domain>
FRONTEND_ORIGIN=https://dischargesummaries.<domain>

STUDY_TYPE=dischargesummaries
STUDY_TITLE=Discharge Summaries Validation
STUDY_LABEL=Discharge Summary

DB_NAME=cnics_dischargesummaries
DB_USER=ds_user
DB_PASSWORD=<new secret>
DB_ROOT_PASSWORD=<new secret>

INTERNAL_SUBNET=172.31.223.0/24     # must not overlap scans' 172.31.222.0/24
INTERNAL_GATEWAY=172.31.223.1

CLINICAL_SITES=<site codes for this study>
```

Leave the four workflow settings (`ENABLE_SCRUBBING`, `ENABLE_SCREENING`,
`ENABLE_SENDING`, `REVIEWER_COUNT`) **unset**. The study type supplies:
scrub on, screen off, send off, one reviewer.

### Patient roster bridge

- **Dev (socket)**: keep `CNICS_DATA_BRIDGE_MODE=socket` and the same
  `CNICS_DATA_SOCKET_DIR` as `scans`.
- **Prod (tcp, shared tunnel)**:

  ```ini
  CNICS_DATA_BRIDGE_MODE=tcp
  CNICS_DATA_DB_HOST=172.31.222.1   # the scans gateway, where the tunnel listens; NOT this stack's gateway
  CNICS_DATA_DB_PORT=13306
  CNICS_DATA_DB_USER=<same read-only bridge user as scans>
  CNICS_DATA_DB_PASSWORD=<same>
  ```

  Then allow this stack's containers through the host firewall to the tunnel,
  and make the rule durable in puppet next to the existing one:

  ```bash
  sudo iptables -I INPUT 1 -s 172.31.223.0/24 -d 172.31.222.1 -p tcp --dport 13306 -j ACCEPT
  ```

  **Dependency**: `172.31.222.1` exists only while the `scans` network
  exists. `docker compose down` on `scans` takes the tunnel's listen
  address away, and this deployment loses patient lookups until `scans` is
  back up. `docker compose stop` on `scans` is safe.

## 3. Check before starting

```bash
docker compose config | grep -A3 ipam      # expect 172.31.223.0/24 / 172.31.223.1
docker network ls | grep cnics             # scans' network still listed, untouched
```

## 4. Start

```bash
docker compose up -d --build
docker compose logs mariadb | grep 04-study-schema
#   expect: Applied /docker-entrypoint-initdb.d/studies/dischargesummaries.sql
#   a "applying mci.sql instead" warning means STUDY_TYPE is wrong — stop and fix
docker compose logs backend | grep -i "No usable study module"   # expect nothing
```

## 5. Apache vhost

Copy the `scans` vhost. Change its server name to this `SERVER_NAME` and its
proxy targets to this `EXTERNAL_PORT` / `BACKEND_EXTERNAL_PORT`. Keep the
same LDAP basic-auth and `X-Remote-User` forwarding.

## 6. Data-isolation check before go-live (required)

The constitution's "Data isolation audits" gate requires both of these to be
verified before a new study deployment goes live. Record the results in the
deployment notes.

1. **The database user cannot read another study's schema.** From this stack:

   ```bash
   docker compose exec mariadb sh -c 'mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SHOW DATABASES;"'
   ```

   The output should list only `information_schema` and this deployment's
   `DB_NAME`, plus the FEDERATED roster proxy inside it. It must not list a
   `scans` or `mci` database. Each stack has its own MariaDB container, so
   another study's schema should not exist here at all.
2. **The web origin is distinct.** This deployment's `SERVER_NAME` and
   `FRONTEND_ORIGIN` must differ from every other study's on the VM:

   ```bash
   grep -H '^\(SERVER_NAME\|FRONTEND_ORIGIN\)=' /srv/*/.env
   ```

   Each value should appear once.

## 7. Smoke test (SC-001)

1. As admin, add one event for a patient at site A.
2. As a site-A uploader, upload a PDF. The confirmation says the next step is scrubbing.
3. As a site-B uploader, try to upload for the same event. It is refused.
4. As admin, open To Be Scrubbed, download the original, and upload the scrubbed copy.
5. As admin, assign to a reviewer. Only one reviewer field is shown, and there is no To Be Sent list.
6. As the reviewer, the event is in the queue and the document link gives the scrubbed copy. Phase A shows "review form not available yet". Phase B: submit, and the event is Done.
7. Check that `scans` still serves normally.
