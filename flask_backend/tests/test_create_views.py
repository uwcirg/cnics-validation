from unittest.mock import MagicMock, patch
import flask_backend.create_views as cv

@patch('flask_backend.create_views.connector.connect')
def test_create_views(mock_connect):
    mock_conn = MagicMock()
    mock_cursor = MagicMock()
    mock_conn.cursor.return_value = mock_cursor
    mock_connect.return_value = mock_conn

    cv.create_views()

    mock_connect.assert_called()
    assert "CREATE OR REPLACE VIEW" in mock_cursor.execute.call_args.args[0]
    assert "JOIN uw_patients2" in mock_cursor.execute.call_args.args[0]
    mock_conn.commit.assert_called()
