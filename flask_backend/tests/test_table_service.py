from unittest.mock import MagicMock, patch
import flask_backend.table_service as ts


@patch('flask_backend.table_service.get_pool')
def test_get_table_data(mock_get_pool):
    mock_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_cursor.fetchall.return_value = [{'id': 1}]
    mock_conn.cursor.return_value = mock_cursor
    mock_get_pool.return_value.get_connection.return_value = mock_conn

    rows = ts.get_table_data('events')

    mock_get_pool.assert_called()
    mock_cursor.execute.assert_called_with('SELECT * FROM `events` LIMIT 100')
    assert rows == [{'id': 1}]


@patch('flask_backend.table_service.get_pool')
def test_get_events_need_packets(mock_get_pool):
    mock_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_cursor.fetchall.return_value = [{'ID': 1}]
    mock_conn.cursor.return_value = mock_cursor
    mock_get_pool.return_value.get_connection.return_value = mock_conn

    rows = ts.get_events_need_packets()

    mock_get_pool.assert_called()
    query = mock_cursor.execute.call_args.args[0]
    assert "GROUP BY e.id" in query
    assert "JOIN uw_patients2" in query
    assert rows == [{'ID': 1}]


@patch('flask_backend.table_service.get_pool')
def test_get_events_for_review(mock_get_pool):
    mock_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_cursor.fetchall.return_value = [{'ID': 2}]
    mock_conn.cursor.return_value = mock_cursor
    mock_get_pool.return_value.get_connection.return_value = mock_conn

    rows = ts.get_events_for_review()

    mock_get_pool.assert_called()
    query = mock_cursor.execute.call_args.args[0]
    assert "WHERE e.status = 'uploaded'" in query
    assert "GROUP BY e.id" in query
    assert rows == [{'ID': 2}]


@patch('flask_backend.table_service.get_pool')
def test_get_events_for_reupload(mock_get_pool):
    mock_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_cursor.fetchall.return_value = [{'ID': 3}]
    mock_conn.cursor.return_value = mock_cursor
    mock_get_pool.return_value.get_connection.return_value = mock_conn

    rows = ts.get_events_for_reupload()

    mock_get_pool.assert_called()
    query = mock_cursor.execute.call_args.args[0]
    assert "WHERE e.status = 'rejected'" in query
    assert "GROUP BY e.id" in query
    assert rows == [{'ID': 3}]


@patch('flask_backend.table_service.get_pool')
def test_get_event_status_summary(mock_get_pool):
    mock_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_cursor.fetchall.return_value = [{'status': 'created', 'count': 3}]
    mock_conn.cursor.return_value = mock_cursor
    mock_get_pool.return_value.get_connection.return_value = mock_conn

    summary = ts.get_event_status_summary()

    mock_get_pool.assert_called()
    mock_cursor.execute.assert_called()
    assert summary == {'created': 3}
