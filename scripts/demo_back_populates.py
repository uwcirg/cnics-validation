import os
from flask_backend import models


def main() -> None:
    # Ensure DB envs exist; expect docker-compose or local MySQL to be running
    session = models.get_session()

    # Create a transient Event and related objects without flushing first
    event = models.Events(
        patient_id=1,
        creator_id=1,
        status='created',
        add_date=models.datetime.date.today(),
        event_date=models.datetime.date.today(),
    )

    # With back_populates present (models.py), SQLAlchemy keeps both sides in sync
    crit = models.Criterias(name='example', value='with-back-populates')
    event.criterias.append(crit)

    # Because of back_populates, crit.event now points to event (even before flush)
    print('Has bidirectional link before flush? ', crit.event is event)

    # Persist and query back
    session.add(event)
    session.commit()

    fetched = session.get(models.Events, event.id)
    print('Fetched event criterias count: ', len(fetched.criterias))
    if fetched.criterias:
        print('Fetched first criteria backref exists? ', fetched.criterias[0].event_id == fetched.id)


if __name__ == '__main__':
    main()


