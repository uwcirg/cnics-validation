#!/usr/bin/env python3
"""
Script to create test users for the DevAuthTester component.
Run this script to populate the database with test users.
"""

import os
import sys
import logging
from flask_backend.logging_config import configure_logging
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from flask_backend.models import get_session, Users

def create_test_users():
    """Create test users for development testing."""
    session = get_session()
    
    try:
        # Check if test users already exist
        existing_users = session.query(Users).filter(
            Users.login.in_(['admin_test', 'uploader_test', 'reviewer_test', 'third_reviewer_test', 'multi_role_test', 'basic_test'])
        ).all()
        
        if existing_users:
            logging.getLogger(__name__).info(
                "existing_test_users",
                extra={"count": len(existing_users)},
            )
            return
        
        # Create test users
        test_users = [
            {
                'username': 'Admin Test User',
                'login': 'admin_test',
                'first_name': 'Admin',
                'last_name': 'Test',
                'site': 'TEST',
                'uploader_flag': 1,
                'reviewer_flag': 1,
                'third_reviewer_flag': 0,
                'admin_flag': 1
            },
            {
                'username': 'Uploader Test User',
                'login': 'uploader_test',
                'first_name': 'Uploader',
                'last_name': 'Test',
                'site': 'TEST',
                'uploader_flag': 1,
                'reviewer_flag': 0,
                'third_reviewer_flag': 0,
                'admin_flag': 0
            },
            {
                'username': 'Reviewer Test User',
                'login': 'reviewer_test',
                'first_name': 'Reviewer',
                'last_name': 'Test',
                'site': 'TEST',
                'uploader_flag': 0,
                'reviewer_flag': 1,
                'third_reviewer_flag': 0,
                'admin_flag': 0
            },
            {
                'username': 'Third Reviewer Test User',
                'login': 'third_reviewer_test',
                'first_name': 'Third',
                'last_name': 'Reviewer',
                'site': 'TEST',
                'uploader_flag': 0,
                'reviewer_flag': 1,
                'third_reviewer_flag': 1,
                'admin_flag': 0
            },
            {
                'username': 'Multi Role Test User',
                'login': 'multi_role_test',
                'first_name': 'Multi',
                'last_name': 'Role',
                'site': 'TEST',
                'uploader_flag': 1,
                'reviewer_flag': 1,
                'third_reviewer_flag': 0,
                'admin_flag': 0
            },
            {
                'username': 'Basic Test User',
                'login': 'basic_test',
                'first_name': 'Basic',
                'last_name': 'User',
                'site': 'TEST',
                'uploader_flag': 0,
                'reviewer_flag': 0,
                'third_reviewer_flag': 0,
                'admin_flag': 0
            }
        ]
        
        for user_data in test_users:
            user = Users(**user_data)
            session.add(user)
            logging.getLogger(__name__).info(
                "created_test_user",
                extra={"username": user_data["username"], "login": user_data["login"]},
            )
        
        session.commit()
        logging.getLogger(__name__).info(
            "created_test_users_summary",
            extra={"created": len(test_users)},
        )
        
    except Exception as e:
        session.rollback()
        logging.getLogger(__name__).exception("failed_to_create_test_users")
        raise
    finally:
        session.close()

if __name__ == '__main__':
    configure_logging()
    create_test_users()
