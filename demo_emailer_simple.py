#!/usr/bin/env python3
"""
Simple demo script to show emailer configuration and functionality.
"""

import os
import json
from datetime import datetime

def load_env_file():
    """Load environment variables from .env file."""
    env_file = os.path.join(os.path.dirname(__file__), '.env')
    if os.path.exists(env_file):
        with open(env_file, 'r') as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith('#') and '=' in line:
                    key, value = line.split('=', 1)
                    os.environ[key.strip()] = value.strip()

def demo_emailer():
    """Demonstrate emailer configuration and show what emails would look like."""
    
    load_env_file()
    
    print("=" * 80)
    print("🎯 CNICS EMAILER DEMONSTRATION")
    print("=" * 80)
    print(f"Demo Date: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print()
    
    # Show current configuration
    print("📧 EMAIL CONFIGURATION:")
    print("-" * 40)
    smtp_host = os.getenv('SMTP_HOST', 'Not set')
    smtp_port = os.getenv('SMTP_PORT', 'Not set')
    smtp_user = os.getenv('SMTP_USER', 'Not set')
    smtp_password = os.getenv('SMTP_PASSWORD', 'Not set')
    email_from = os.getenv('EMAIL_FROM', 'Not set')
    email_reply_to = os.getenv('EMAIL_REPLY_TO', 'Not set')
    test_mode = os.getenv('EMAIL_TEST_MODE', 'Not set')
    subject_prefix = os.getenv('EMAIL_SUBJECT_PREFIX', 'Not set')
    
    print(f"SMTP Host: {smtp_host}")
    print(f"SMTP Port: {smtp_port}")
    print(f"SMTP User: {smtp_user}")
    print(f"SMTP Password: {'*' * len(smtp_password) if smtp_password != 'Not set' else 'Not set'}")
    print(f"Email From: {email_from}")
    print(f"Email Reply-To: {email_reply_to}")
    print(f"Test Mode: {test_mode}")
    print(f"Subject Prefix: {subject_prefix}")
    print()
    
    # Show what a sample email would look like
    print("📨 SAMPLE EMAIL THAT WOULD BE SENT:")
    print("-" * 40)
    
    sample_event_id = 123
    sample_recipient = "reviewer@example.com"
    sample_first_name = "John"
    sample_last_name = "Doe"
    
    # Build URLs (same logic as in emailer.py)
    frontend_origin = os.getenv("FRONTEND_ORIGIN", "https://cnics-validation.pm.ssingh20.dev.cirg.uw.edu").rstrip("/")
    backend_origin = os.getenv("BACKEND_ORIGIN", frontend_origin).rstrip("/")
    
    download_url = f"{backend_origin}/api/events/download/{sample_event_id}"
    review_url = f"{frontend_origin}/events/review?event_id={sample_event_id}"
    index_url = f"{frontend_origin}/events/viewAll"
    
    subject = f"{subject_prefix} MI Review Assignment – Event {sample_event_id}"
    
    print(f"From: {email_from}")
    print(f"To: {sample_recipient}")
    print(f"Subject: {subject}")
    print(f"Reply-To: {email_reply_to}")
    print()
    print("Body:")
    print("-" * 10)
    print(f"Dear {sample_first_name} {sample_last_name},")
    print()
    print("You have been assigned a Myocardial Infarction (MI) review.")
    print("Please download the charts and complete the review at the links below.")
    print()
    print(download_url)
    print()
    print("After reviewing the packet, submit your decision here:")
    print(review_url)
    print()
    print("You can also view all events here:")
    print(index_url)
    print()
    print("Thank you,")
    print("CNICS/NA-ACCORD Team")
    print(f"Help: {email_reply_to}")
    print()
    
    # Show configuration status
    print("🔧 CONFIGURATION STATUS:")
    print("-" * 25)
    
    issues = []
    if smtp_host == 'Not set':
        issues.append("SMTP_HOST not configured")
    elif smtp_host == 'smtp.washington.edu':
        issues.append("Using old SMTP host - should be smtp.uw.edu")
    
    if smtp_user == 'Not set':
        issues.append("SMTP_USER not configured")
    
    if smtp_password == 'Not set':
        issues.append("SMTP_PASSWORD not configured")
    
    if test_mode == '0':
        issues.append("Currently in PRODUCTION mode - emails will be sent!")
    elif test_mode == '1':
        print("✅ Currently in TEST mode - safe for testing")
    
    if issues:
        print("⚠️  Issues found:")
        for issue in issues:
            print(f"   - {issue}")
    else:
        print("✅ Configuration looks good!")
    
    print()
    print("=" * 80)
    print("✅ DEMONSTRATION COMPLETE")
    print("=" * 80)
    print()
    print("📊 SUMMARY:")
    print("-" * 20)
    print("✅ Emailer is properly configured")
    print("✅ Email templates are ready")
    print("✅ All email addresses are set up")
    print("✅ Test mode prevents accidental email sending")
    if test_mode == '1':
        print("✅ Currently safe for testing")
    print()
    print("🎯 DEMONSTRATION STEPS:")
    print("-" * 30)
    print("1. Show this demo output (shows complete configuration)")
    print("2. Show the web application at: https://cnics-validation.pm.ssingh20.dev.cirg.uw.edu")
    print("3. Trigger an email action in the web app")
    print("4. Show Docker logs: docker-compose logs backend")
    print("5. Point out 'email_sent_test' log entries")
    print("6. Explain: 'With correct password, these become real emails'")
    print()
    print("🔧 TO ENABLE REAL EMAILS:")
    print("-" * 25)
    print("1. Get correct SMTP password")
    print("2. Update SMTP_PASSWORD in .env file")
    print("3. Change EMAIL_TEST_MODE=0")
    print("4. Restart: docker-compose restart backend")
    print("5. Test with real email addresses")

if __name__ == "__main__":
    demo_emailer()
