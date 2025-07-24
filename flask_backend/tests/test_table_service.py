from unittest.mock import MagicMock, patch
import flask_backend.table_service as ts


@patch('flask_backend.table_service.models.get_session')
def test_get_table_data(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'id': 1}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_table_data('events')

    mock_get_session.assert_called()
    mock_session.execute.assert_called()
    assert rows == [{'id': 1}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_need_packets(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 1}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_need_packets()

    mock_get_session.assert_called()
    query = mock_session.execute.call_args.args[0]
    assert "GROUP BY events.id" in str(query)
    assert "JOIN patients" in str(query)
    assert rows == [{'ID': 1}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_for_review(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 2}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_for_review()

    mock_get_session.assert_called()
    query = mock_session.execute.call_args.args[0]
    assert 'events.status' in str(query)
    assert rows == [{'ID': 2}]


@patch('flask_backend.table_service.models.get_session')
def test_get_events_for_reupload(mock_get_session):
    mock_session = MagicMock()
    mock_session.execute.return_value.mappings.return_value.all.return_value = [
        {'ID': 3}
    ]
    mock_get_session.return_value = mock_session

    rows = ts.get_events_for_reupload()

    mock_get_session.assert_called()
    query = mock_session.execute.call_args.args[0]
    assert 'events.status' in str(query)
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
