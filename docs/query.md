# Query to update status with fresh migration
```sql
UPDATE workers 
SET status = CASE 
    WHEN status = 'bjs_network_exception' THEN 'network_exception'
    WHEN status = 'bjs_empty_response' THEN 'empty_response'
    WHEN status = 'bjs_user_not_found' THEN 'user_not_found'
    WHEN status = 'bjs_bad_request' THEN 'bad_request'
    WHEN status = 'bjs_possibly_change_username' THEN 'possibly_change_username'
    WHEN status = 'bjs_ip_block' THEN 'ip_block'
    WHEN status = 'bjs_2fa_required' THEN '2fa_required'
    WHEN status = 'bjs_2fa_failed' THEN '2fa_failed'
    WHEN status = 'bjs_feedback_required' THEN 'feedback_required'
    WHEN status = 'bjs_incorrect_password' THEN 'incorrect_password'
    WHEN status = 'bjs_need_change_password' THEN 'need_change_password'
    WHEN status = 'bjs_consent_required' THEN 'consent_required'
    WHEN status = 'bjs_challenge_required' THEN 'challenge_required'
    WHEN status = 'bjs_account_disabled' THEN 'account_disabled'
    WHEN status = 'bjs_account_challenged' THEN 'account_challenged'
    WHEN status = 'bjs_account_banned' THEN 'account_banned'
    WHEN status = 'bjs_account_restricted' THEN 'account_restricted'
    ELSE status
END
WHERE status LIKE 'bjs_%';
```
