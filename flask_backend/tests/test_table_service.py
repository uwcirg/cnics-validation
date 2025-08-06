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
    query = mock_session.execute.call_args.args[0]
    assert "GROUP BY events.id" in str(query)
    assert "JOIN patients" in str(query)
    assert mock_session.execute.call_args.args[1] == {'status': 'created', 'limit': 5, 'offset': 0}
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
    query = mock_session.execute.call_args.args[0]
    assert 'events.status' in str(query)
    assert mock_session.execute.call_args.args[1] == {'status': 'uploaded', 'limit': 6, 'offset': 0}
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
    query = mock_session.execute.call_args.args[0]
    assert 'events.status' in str(query)
    assert mock_session.execute.call_args.args[1] == {'status': 'rejected', 'limit': 7, 'offset': 0}
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


@patch('flask_backend.table_service.models.get_external_session')
@patch('flask_backend.table_service.models.get_session')
def test_get_events_with_patient_site(mock_get_session, mock_get_external_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'id': 1, 'patient_id': 10}
    ]
    mock_get_session.return_value = mock_session

    mock_ext_session = MagicMock()
    mock_ext_session.execute.return_value.mappings.return_value.all.return_value = [
        {'id': 10, 'site': 'UW'}
    ]
    mock_get_external_session.return_value = mock_ext_session

    rows = ts.get_events_with_patient_site()

    mock_get_session.assert_called()
    mock_get_external_session.assert_called()
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
