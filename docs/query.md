# Query to update status with fresh migration
```sql
UPDATE workers 
SET status = CASE 
    -- Remove both prefix and suffix
    WHEN status = 'bjs_account_disabled_exception' THEN 'account_disabled'
    -- Remove bjs_ prefix
    WHEN status = 'bjs_2fa_failed' THEN '2fa_failed'
    WHEN status = 'bjs_2fa_required' THEN '2fa_required'
    WHEN status = 'bjs_account_banned' THEN 'account_banned'
    WHEN status = 'bjs_account_disabled' THEN 'account_disabled'
    WHEN status = 'bjs_aktif' THEN 'aktif'
    WHEN status = 'bjs_aktif_challenged' THEN 'aktif_challenged'
    WHEN status = 'bjs_bad_request' THEN 'bad_request'
    WHEN status = 'bjs_challenge_required_exception' THEN 'challenge_required'
    WHEN status = 'bjs_consent_required_exception' THEN 'consent_required'
    WHEN status = 'bjs_empty_response_exception' THEN 'empty_response'
    WHEN status = 'bjs_incorrect_password_exception' THEN 'incorrect_password'
    WHEN status = 'bjs_need_change_password_exception' THEN 'need_change_password'
    WHEN status = 'bjs_new_login' THEN 'new_login'
    WHEN status = 'bjs_no_hope' THEN 'no_hope'
    WHEN status = 'bjs_possibly_change_username' THEN 'possibly_change_username'
    -- Keep existing transformations
    WHEN status = 'bjs_network_exception' THEN 'network_exception'
    WHEN status = 'bjs_empty_response' THEN 'empty_response'
    WHEN status = 'bjs_user_not_found' THEN 'user_not_found'
    WHEN status = 'bjs_ip_block' THEN 'ip_block'
    WHEN status = 'bjs_feedback_required' THEN 'feedback_required'
    WHEN status = 'bjs_challenge_required' THEN 'challenge_required'
    WHEN status = 'bjs_account_challenged' THEN 'account_challenged'
    WHEN status = 'bjs_account_restricted' THEN 'account_restricted'
    ELSE status
END
WHERE status LIKE 'bjs_%';


update workers 
set status ='relogin'
where status in ('0', '[]', 'empty_response', 'aktif_challenged')
```
