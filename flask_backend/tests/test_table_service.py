import datetime
from unittest.mock import MagicMock, patch
import flask_backend.table_service as ts


@patch('flask_backend.table_service.models.get_session')
def test_get_table_data(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'id': 1}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_table_data('events', 10, 2)

    mock_get_session.assert_called()
    mock_session.execute.assert_called()
    assert mock_session.execute.call_args.args[1] == {'limit': 10, 'offset': 2}
    assert rows == [{'id': 1}]


@patch('flask_backend.table_service.models.get_session')
def test_get_table_data_no_limit(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'id': 1},
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_table_data('events', None, 0)

    query = str(mock_session.execute.call_args.args[0])
    params = mock_session.execute.call_args.args[1]
    assert 'LIMIT' not in query.upper()
    assert params == {}
    assert rows == [{'id': 1}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_need_packets(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 1}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_need_packets(5, 0)

    mock_get_session.assert_called()
    mock_session.execute.assert_called()
    # get_events_by_status_with_total runs more than one execute (count + data).
    # Look across all calls: at least one should be the data query joining
    # patients and filtering on status='created', and the params across all
    # calls should include the expected status/limit/offset.
    all_queries = [str(c.args[0]) for c in mock_session.execute.call_args_list]
    all_params = [c.args[1] for c in mock_session.execute.call_args_list if len(c.args) > 1]
    assert any('JOIN patients_view' in q for q in all_queries)
    assert any('e.status = :status' in q for q in all_queries)
    assert any(p.get('status') == 'created' for p in all_params)
    assert any(p.get('limit') == 5 and p.get('offset') == 0 for p in all_params)
    assert rows == [{'ID': 1}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_need_packets_no_limit(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 1},
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_need_packets(None, 0)

    query = str(mock_session.execute.call_args.args[0])
    params = mock_session.execute.call_args.args[1]
    assert 'LIMIT' not in query.upper()
    assert params == {'status': 'created'}
    assert rows == [{'ID': 1}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_for_review(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 2}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_for_review(6, 0)

    mock_get_session.assert_called()
    mock_session.execute.assert_called()
    all_queries = [str(c.args[0]) for c in mock_session.execute.call_args_list]
    all_params = [c.args[1] for c in mock_session.execute.call_args_list if len(c.args) > 1]
    assert any('e.status = :status' in q for q in all_queries)
    assert any(p.get('status') == 'sent' for p in all_params)
    assert any(p.get('limit') == 6 and p.get('offset') == 0 for p in all_params)
    assert rows == [{'ID': 2}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_for_reupload(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 3}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_for_reupload(7, 0)

    mock_get_session.assert_called()
    mock_session.execute.assert_called()
    all_queries = [str(c.args[0]) for c in mock_session.execute.call_args_list]
    all_params = [c.args[1] for c in mock_session.execute.call_args_list if len(c.args) > 1]
    assert any('e.status = :status' in q for q in all_queries)
    # get_events_for_reupload filters on status='uploaded' (events that have
    # been uploaded but need re-upload by the uploader's site), not 'rejected'.
    assert any(p.get('status') == 'uploaded' for p in all_params)
    assert any(p.get('limit') == 7 and p.get('offset') == 0 for p in all_params)
    assert rows == [{'ID': 3}]


@patch('flask_backend.table_service.models.get_session')
def test_get_event_status_summary(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.all.return_value = [('created', 3)]
    mock_get_session.return_value = mock_session

    summary = ts.get_event_status_summary()

    mock_get_session.assert_called()
    mock_session.execute.assert_called()
    assert summary == {'created': 3}


@patch('flask_backend.table_service.models.get_session')
def test_get_events_with_patient_site(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'id': 1, 'patient_id': 10, 'site': 'UW'}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_with_patient_site()

    mock_get_session.assert_called()
    query = str(mock_session.execute.call_args.args[0])
    assert 'JOIN patients_view' in query
    assert rows == [{'id': 1, 'patient_id': 10, 'site': 'UW'}]


@patch('flask_backend.table_service.models.Users')
@patch('flask_backend.table_service.models.get_session')
def test_create_user(mock_get_session, mock_users):
    mock_session = MagicMock()
    mock_get_session.return_value = mock_session
    user_instance = MagicMock()
    user_instance.id = 1
    user_instance.username = 'u'
    user_instance.login = 'l'
    user_instance.first_name = 'f'
    user_instance.last_name = 'l'
    user_instance.site = 's'
    user_instance.uploader_flag = 1
    user_instance.reviewer_flag = 0
    user_instance.third_reviewer_flag = 0
    user_instance.admin_flag = 1
    mock_users.return_value = user_instance

    data = {
        'username': 'u',
        'login': 'l',
        'first_name': 'f',
        'last_name': 'l',
        'site': 's',
        'uploader': True,
        'reviewer': False,
        'third_reviewer': False,
        'admin': True,
    }

    result = ts.create_user(data)

    mock_get_session.assert_called()
    mock_session.add.assert_called_with(user_instance)
    mock_session.commit.assert_called()
    mock_session.close.assert_called()
    mock_users.assert_called_with(
        username='u',
        login='l',
        first_name='f',
        last_name='l',
        site='s',
        uploader_flag=1,
        reviewer_flag=0,
        third_reviewer_flag=0,
        admin_flag=1,
    )
    assert result['id'] == 1


def _event_details_session(event_row, criteria_rows):
    """Build a mock session for get_event_details, which runs two queries.

    The first fetches the single event row (.mappings().first()); the second
    fetches its criteria (.mappings().all()).
    """
    event_result = MagicMock()
    event_result.mappings.return_value.first.return_value = event_row
    criteria_result = MagicMock()
    criteria_result.mappings.return_value.all.return_value = criteria_rows

    mock_session = MagicMock()
    mock_session.execute.side_effect = [event_result, criteria_result]
    return mock_session


@patch('flask_backend.table_service.models.get_session')
def test_get_event_details_returns_criteria_name_and_value(mock_get_session):
    """Criteria come back as structured pairs, ordered by name then id."""
    mock_get_session.return_value = _event_details_session(
        {'id': 7, 'patient_id': 3},
        [
            {'name': 'ECG', 'value': 'ST elevation'},
            {'name': 'Troponin', 'value': '0.42 ng/mL'},
        ],
    )

    details = ts.get_event_details(7)

    assert details['criteria'] == [
        {'name': 'ECG', 'value': 'ST elevation'},
        {'name': 'Troponin', 'value': '0.42 ng/mL'},
    ]
    # The ordering is applied in SQL, not on the client, so assert the query
    # asks for it. FR-008 requires a total order that cannot vary between loads.
    criteria_query = str(mock_session_query(mock_get_session, index=1))
    assert 'FROM criterias' in criteria_query
    assert 'ORDER BY c.name, c.id' in criteria_query


def mock_session_query(mock_get_session, index):
    """Return the SQL text of the Nth execute() call on the mocked session."""
    return mock_get_session.return_value.execute.call_args_list[index].args[0]


@patch('flask_backend.table_service.models.get_session')
def test_get_event_details_criteria_query_is_scoped_to_the_event(mock_get_session):
    """The criteria lookup filters on event_id rather than fetching all rows."""
    mock_get_session.return_value = _event_details_session({'id': 7}, [])

    ts.get_event_details(7)

    params = mock_get_session.return_value.execute.call_args_list[1].args[1]
    assert params == {'event_id': 7}


@patch('flask_backend.table_service.models.get_session')
def test_get_event_details_duplicate_criteria_names_order_deterministically(
    mock_get_session,
):
    """Two criteria sharing a name still come back in a stable order."""
    mock_get_session.return_value = _event_details_session(
        {'id': 7},
        [
            {'name': 'Troponin', 'value': '0.10 ng/mL'},
            {'name': 'Troponin', 'value': '0.42 ng/mL'},
        ],
    )

    details = ts.get_event_details(7)

    assert [c['value'] for c in details['criteria']] == ['0.10 ng/mL', '0.42 ng/mL']


@patch('flask_backend.table_service.models.get_session')
def test_get_event_details_no_criteria_returns_empty_list(mock_get_session):
    """An event with no criteria yields [] and never None.

    Criteria are optional, so this is a legitimate state; the upload page must
    have one shape to render for it and must stay uploadable (FR-006b).
    """
    mock_get_session.return_value = _event_details_session({'id': 7}, [])

    details = ts.get_event_details(7)

    assert details['criteria'] == []
    assert details['criteria'] is not None


@patch('flask_backend.table_service.models.get_session')
def test_get_event_details_preserves_existing_keys(mock_get_session):
    """Regression guard: the criteria addition must be purely additive.

    EventScrub and EventScreen consume this same payload; if any pre-existing
    key changed name, type, or serialization, those pages would break.
    """
    mock_get_session.return_value = _event_details_session(
        {
            'id': 7,
            'patient_id': 3,
            'site_patient_id': 'UW-00412',
            'site': 'UW',
            'status': 'created',
            'event_date': datetime.date(2026, 3, 14),
            'upload_date': None,
            'uploader_username': None,
        },
        [{'name': 'ECG', 'value': 'ST elevation'}],
    )

    details = ts.get_event_details(7)

    assert details['id'] == 7
    assert details['patient_id'] == 3
    assert details['site_patient_id'] == 'UW-00412'
    assert details['site'] == 'UW'
    assert details['status'] == 'created'
    assert details['upload_date'] is None
    assert details['uploader_username'] is None
    # Dates stay ISO-serialized, which is what the date controls elsewhere parse.
    assert details['event_date'] == '2026-03-14'


@patch('flask_backend.table_service.models.get_session')
def test_get_event_details_missing_event_returns_empty_dict(mock_get_session):
    """A nonexistent event short-circuits before the criteria query runs."""
    mock_get_session.return_value = _event_details_session(None, [])

    details = ts.get_event_details(999)

    assert details == {}
    assert mock_get_session.return_value.execute.call_count == 1
