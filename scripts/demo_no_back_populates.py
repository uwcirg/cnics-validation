import os
from flask_backend import models2 as models


def main() -> None:
    session = models.get_session()

    event = models.Events(
        patient_id=1,
        creator_id=1,
        status='created',
        add_date=models.datetime.date.today(),
        event_date=models.datetime.date.today(),
    )

    # Without back_populates, appending to event.criterias will NOT set crit.event automatically
    crit = models.Criterias(name='example', value='without-back-populates')
    event.criterias.append(crit)

    print('Has bidirectional link before flush? ', getattr(crit, 'event', None) is event)

    # Persist
    session.add(event)
    session.commit()

    # After commit, because no back_populates is defined, the ORM will not automatically
    # maintain the in-memory backref. crit.event may still be None in this session context.
    print('Has bidirectional link after commit? ', getattr(crit, 'event', None) is event)

    fetched = session.get(models.Events, event.id)
    print('Fetched event criterias count: ', len(fetched.criterias))
    if fetched.criterias:
        # The foreign key event_id was set by flush because Criterias.event_id is FK via relationship/parent
        # but the reverse attribute on the child is not maintained automatically without back_populates.
        print('Fetched first criteria event_id matches? ', fetched.criterias[0].event_id == fetched.id)


if __name__ == '__main__':
    main()


