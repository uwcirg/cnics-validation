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
