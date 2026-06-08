# Connecting `patients_view` to the upstream `cnics_data` roster

The application's `patients_view` is a UNION of two sources:

1. the locally-owned `uw_patients2` table (in this deployment's own DB), and
2. a **FederatedX proxy** (`cnics_data_patient_remote`) of the upstream
   `cnics_data.Patient` table.

`init/06-create-patients-view.sh` builds (2) and (3 = the view) on the mariadb
container. How the FederatedX proxy reaches `cnics_data` is selected by the
`CNICS_DATA_BRIDGE_MODE` environment variable. There are two supported
topologies:

| Mode     | When                                                | Transport                                  |
| -------- | --------------------------------------------------- | ------------------------------------------ |
| `socket` | `cnics_data` runs on the **same VM** (dev/co-located) | host MariaDB **Unix socket**, bind-mounted in |
| `tcp`    | `cnics_data` runs on a **separate VM** (prod)         | **TCP** to a forward SSH (autossh) tunnel  |

> The FederatedX engine plugin is loaded from `mariadb/conf.d/custom.cnf` at
> server start; it is required for both modes.

`init/06` is **idempotent** (`DROP … IF EXISTS` / `CREATE OR REPLACE`), so it
is safe to re-run.

---

## Important: when `init/06` actually runs

Docker `docker-entrypoint-initdb.d` scripts run **only on first DB
initialization** — i.e. when the `mariadb-data` volume is empty. On any
deployment with an existing data volume (the normal case for an
already-running server), adding or changing `init/06` does **not** re-run it.
Run it manually after changing bridge configuration:

```bash
docker compose exec mariadb bash /docker-entrypoint-initdb.d/06-create-patients-view.sh
```

The script writes the view into `$MYSQL_DATABASE` (= the `DB_NAME` from
`.env`), which is the same DB the backend reads from. If a "`<db>.patients_view`
doesn't exist" error appears in the app, the usual cause is that `init/06` has
not been run (or was interrupted) against that DB.

---

## Disabling the bridge (single-instance / dev fallback)

Leaving `CNICS_DATA_DB_USER` **empty** disables the bridge entirely. `init/06`
then defines `patients_view` over `uw_patients2` alone and exits. Use this when
there is no upstream `cnics_data` to connect to.

---

## Mode `socket` — co-located `cnics_data` (dev)

`cnics_data` runs in MariaDB on the **same VM** as these containers, bound to
that host's loopback. The proxy reaches it over the host's Unix socket. A
container cannot reach the host loopback over TCP, so the socket is the path.

### `.env`

```bash
CNICS_DATA_BRIDGE_MODE=socket
# Host directory containing mysqld.sock; bind-mounted to /run/cnics_data_host:
CNICS_DATA_SOCKET_DIR=/var/run/mysqld
CNICS_DATA_DB_NAME=cnics_data
CNICS_DATA_DB_TABLE=Patient
CNICS_DATA_DB_USER=<read-only bridge user>
CNICS_DATA_DB_PASSWORD=<secret>
```

`docker-compose.yaml` bind-mounts `${CNICS_DATA_SOCKET_DIR}` to
`/run/cnics_data_host` in the mariadb container; `init/06` reads
`/run/cnics_data_host/mysqld.sock`. (It is a **directory** mount, not a file
mount, so it survives the host server recreating its socket file.)

### Prerequisites

- `cnics_data` MariaDB is running on this VM host and producing
  `${CNICS_DATA_SOCKET_DIR}/mysqld.sock`.
- A read-only bridge user exists on `cnics_data` with `SELECT` on
  `cnics_data.Patient`, usable over the local socket.

### Steps

1. Set the `.env` values above and (re)start so the container picks them up:
   ```bash
   docker compose up -d mariadb
   ```
2. Run the init script:
   ```bash
   docker compose exec mariadb bash /docker-entrypoint-initdb.d/06-create-patients-view.sh
   ```
   Expect `bridge mode: socket (/run/cnics_data_host/mysqld.sock)` and a clean
   return.
3. [Verify](#verifying-the-bridge).

---

## Mode `tcp` — separate `cnics_data` VM (prod)

`cnics_data` runs un-dockerized on a **different VM**, bound to its own
loopback. A **forward** SSH tunnel (`ssh -L`, run by autossh) on the app VM
presents that remote `3306` as a local TCP port, bound to the docker network
gateway so it is reachable from the mariadb container but **not** from the
app VM's external interfaces. The proxy connects over TCP to that endpoint.

This honors the `cnics_data` bind-address policy: the tunnel hits the remote
VM's own `127.0.0.1`, so that server's `bind-address` is never widened.

### Network shape

```
mariadb container ──TCP──> 172.31.222.1:13306 (app VM, docker gateway)
                              │  autossh forward tunnel (ssh -L)
                              ▼
                    cnics_data_vm:127.0.0.1:3306 (MariaDB)
```

### `.env`

```bash
CNICS_DATA_BRIDGE_MODE=tcp
# Docker `internal` network gateway IP, and the tunnel's local listen port:
CNICS_DATA_DB_HOST=172.31.222.1
CNICS_DATA_DB_PORT=13306
CNICS_DATA_DB_NAME=cnics_data
CNICS_DATA_DB_TABLE=Patient
CNICS_DATA_DB_USER=<read-only bridge user>
CNICS_DATA_DB_PASSWORD=<secret>
```

`docker-compose.yaml` **pins** the `internal` network to
`subnet 172.31.222.0/24, gateway 172.31.222.1` so the tunnel has a stable
address to bind to. The socket bind-mount is harmless in this mode (the source
dir is simply empty on a separate-VM host; `init/06` never reads it).

> Keep these four references to `172.31.222.x` in lockstep: the docker gateway,
> `CNICS_DATA_DB_HOST`, the tunnel's `LocalForward` bind address, and the host
> firewall rule. If that subnet ever collides with another network, change all
> four together.

### Prerequisites (one-time, per the two VMs)

On the **app VM**:

1. **Docker network** created with the pinned subnet, so the gateway IP exists.
   If the `internal` network pre-exists from an earlier run, `up -d` will not
   re-subnet it — recreate it once:
   ```bash
   docker compose down
   docker network rm <project>_internal 2>/dev/null   # e.g. cnics-validation_internal
   docker compose up -d
   ip -o addr show | grep 172.31.222.1                # gateway should now exist
   ```
2. **autossh forward tunnel** (managed by puppet `autossh_tunnels`):
   `LocalForward 172.31.222.1:13306 localhost:3306`, `HostName <cnics_data_vm>`,
   `User autossh`. Confirm it is listening:
   ```bash
   ss -ltnp | grep 13306        # expect LISTEN 172.31.222.1:13306
   ```
3. **autossh `known_hosts`** trusts the `cnics_data` VM host key (else the
   tunnel loops on `Host key verification failed`). Verify the fingerprint
   out of band, then seed it:
   ```bash
   ssh-keyscan <cnics_data_vm> 2>/dev/null | ssh-keygen -lf -   # compare fingerprint
   ssh-keyscan <cnics_data_vm> 2>/dev/null | sudo -u autossh tee -a /home/autossh/.ssh/known_hosts
   ```
   Make this durable in puppet (managed `known_hosts` / `sshkey`, or
   `StrictHostKeyChecking accept-new` in the tunnel ssh-config template).
4. **Host firewall** permits the docker subnet to reach the tunnel port on the
   gateway. Without this, the container's connect **times out** (errno 110) and
   `init/06` hangs at `CREATE TABLE … ENGINE=FEDERATED`. The tunnel listener is
   a plain host process (not a docker-published port), so this traffic lands in
   the host `INPUT` chain:
   ```bash
   sudo iptables -I INPUT 1 -s 172.31.222.0/24 -d 172.31.222.1 -p tcp --dport 13306 -j ACCEPT
   ```
   Make this durable in puppet (puppetlabs-firewall or `iptables-persistent`);
   the runtime `iptables -I` is lost on reboot/reload.

On the **`cnics_data` VM**:

5. **autossh public key** authorized in `~autossh/.ssh/authorized_keys`.
6. **Read-only bridge user.** The tunnel makes connections arrive from
   `127.0.0.1`, so grant for `localhost`/`127.0.0.1`, `SELECT` only:
   ```sql
   CREATE USER '<bridge_user>'@'localhost' IDENTIFIED BY '<secret>';
   GRANT SELECT ON cnics_data.Patient TO '<bridge_user>'@'localhost';
   ```
7. MariaDB listening on `127.0.0.1:3306` (bind-address unchanged).

### Steps

1. Set the `.env` values above and (re)start so the container picks them up:
   ```bash
   docker compose up -d mariadb
   ```
2. Confirm the tunnel is reachable from the container before running init
   (this mirrors the exact path FederatedX uses):
   ```bash
   docker compose exec mariadb sh -c \
     'mariadb --connect-timeout=10 -h "$CNICS_DATA_DB_HOST" -P "$CNICS_DATA_DB_PORT" \
        -u "$CNICS_DATA_DB_USER" -p"$CNICS_DATA_DB_PASSWORD" \
        "$CNICS_DATA_DB_NAME" -e "SELECT COUNT(*) FROM \`Patient\`;"'
   ```
   A count means the path is good. (See [Troubleshooting](#troubleshooting-tcp-mode)
   if not.)
3. Run the init script:
   ```bash
   docker compose exec mariadb bash /docker-entrypoint-initdb.d/06-create-patients-view.sh
   ```
   Expect `bridge mode: tcp (172.31.222.1:13306)` and a clean return.
4. [Verify](#verifying-the-bridge).

---

## Verifying the bridge

Query the view in the same DB the app uses:

```bash
docker compose exec mariadb sh -c \
  'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot "$MYSQL_DATABASE" \
     -e "SELECT COUNT(*) FROM patients_view;"'
```

A non-zero count (the upstream `cnics_data.Patient` rows plus any local
`uw_patients2` rows) confirms the proxy is pulling through. The web app's
patient-bearing pages should then load.

---

## Troubleshooting (tcp mode)

| Symptom                                                            | Likely cause                                                                                   | Fix                                                                                              |
| ----------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| `init/06` prints `bridge mode: tcp` then **hangs**                | `CREATE TABLE FEDERATED` connect is stalling — almost always the host firewall dropping the docker subnet | add the `INPUT` ACCEPT rule (prereq 4); re-run `init/06`                                         |
| Container `mariadb` test: `Can't connect … (110)` (timeout)       | host firewall `INPUT` is dropping container→gateway packets                                     | add the `INPUT` ACCEPT rule (prereq 4)                                                           |
| `ss -ltnp \| grep 13306` empty; journal `Host key verification failed` | autossh doesn't trust the remote host key                                                 | seed `known_hosts` (prereq 3)                                                                    |
| journal `Permission denied (publickey)`                           | autossh key not authorized on `cnics_data` VM                                                  | add the key to `~autossh/.ssh/authorized_keys` (prereq 5)                                        |
| `ss` empty; gateway IP missing from `ip addr`                     | docker `internal` network not (re)created with the pinned subnet                               | recreate the network (prereq 1)                                                                  |
| Fast `Access denied for user … '127.0.0.1'`/`'localhost'`         | bridge user grant host is wrong                                                                 | grant for `'<user>'@'localhost'` (prereq 6) — the tunnel arrives from `127.0.0.1`               |
| App: `<db>.patients_view doesn't exist`                           | `init/06` never completed against that DB (interrupted, or not run on an existing volume)       | re-run `init/06`; confirm `DB_NAME` matches the DB the app queries                               |

Inspect the autossh tunnel state with:

```bash
sudo journalctl -u autossh-<cnics_data_vm>-mysql-tunnel -n 50 --no-pager
```
