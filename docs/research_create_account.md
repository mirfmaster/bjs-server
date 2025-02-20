# Create Account

Create account is deprecated due registration from temporary email will be resulted as instant banned

### Creation account flow
```javascript
fetch("https://www.instagram.com/api/v1/web/consent/check_age_eligibility/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.9",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-prefers-color-scheme": "dark",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Google Chrome\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Google Chrome\";v=\"133.0.6943.53\", \"Chromium\";v=\"133.0.6943.53\"",
    "sec-ch-ua-mobile": "?0",
    "sec-ch-ua-model": "\"\"",
    "sec-ch-ua-platform": "\"Linux\"",
    "sec-ch-ua-platform-version": "\"6.9.3\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "x-asbd-id": "129477",
    "x-csrftoken": "Jm9PGNDtD6sypuWkrVZCN-",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1020149475",
    "x-requested-with": "XMLHttpRequest",
    "x-web-device-id": "DB61E481-401D-4E5A-A748-5BFC4E95B96D",
    "x-web-session-id": "dpijut:uukzl7:j929m7",
    "cookie": "csrftoken=Jm9PGNDtD6sypuWkrVZCN-; datr=BYawZ6MFSlWsrJL8NnT6i6Bg; ig_did=DB61E481-401D-4E5A-A748-5BFC4E95B96D; mid=Z7CGBQAEAAHuxKLvLmo0C94T54Fr; ig_nrcb=1; wd=348x923",
    "Referer": "https://www.instagram.com/accounts/emailsignup/",
    "Referrer-Policy": "strict-origin-when-cross-origin"
  },
  "body": "day=16&month=7&year=1988",
  "method": "POST"
});

fetch("https://www.instagram.com/api/v1/accounts/send_verify_email/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.9",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-prefers-color-scheme": "dark",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Google Chrome\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Google Chrome\";v=\"133.0.6943.53\", \"Chromium\";v=\"133.0.6943.53\"",
    "sec-ch-ua-mobile": "?0",
    "sec-ch-ua-model": "\"\"",
    "sec-ch-ua-platform": "\"Linux\"",
    "sec-ch-ua-platform-version": "\"6.9.3\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "x-asbd-id": "129477",
    "x-csrftoken": "Jm9PGNDtD6sypuWkrVZCN-",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1020149475",
    "x-requested-with": "XMLHttpRequest",
    "x-web-device-id": "DB61E481-401D-4E5A-A748-5BFC4E95B96D",
    "x-web-session-id": "dpijut:uukzl7:j929m7",
    "cookie": "csrftoken=Jm9PGNDtD6sypuWkrVZCN-; datr=BYawZ6MFSlWsrJL8NnT6i6Bg; ig_did=DB61E481-401D-4E5A-A748-5BFC4E95B96D; mid=Z7CGBQAEAAHuxKLvLmo0C94T54Fr; ig_nrcb=1; wd=348x923",
    "Referer": "https://www.instagram.com/accounts/emailsignup/",
    "Referrer-Policy": "strict-origin-when-cross-origin"
  },
  "body": "device_id=Z7CGBQAEAAHuxKLvLmo0C94T54Fr&email=dimat33456%40minduls.com",
  "method": "POST"
});

fetch("https://www.instagram.com/api/v1/accounts/check_confirmation_code/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.9",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-prefers-color-scheme": "dark",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Google Chrome\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Google Chrome\";v=\"133.0.6943.53\", \"Chromium\";v=\"133.0.6943.53\"",
    "sec-ch-ua-mobile": "?0",
    "sec-ch-ua-model": "\"\"",
    "sec-ch-ua-platform": "\"Linux\"",
    "sec-ch-ua-platform-version": "\"6.9.3\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "x-asbd-id": "129477",
    "x-csrftoken": "Jm9PGNDtD6sypuWkrVZCN-",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1020149475",
    "x-requested-with": "XMLHttpRequest",
    "x-web-device-id": "DB61E481-401D-4E5A-A748-5BFC4E95B96D",
    "x-web-session-id": "dpijut:uukzl7:j929m7",
    "cookie": "csrftoken=Jm9PGNDtD6sypuWkrVZCN-; datr=BYawZ6MFSlWsrJL8NnT6i6Bg; ig_did=DB61E481-401D-4E5A-A748-5BFC4E95B96D; mid=Z7CGBQAEAAHuxKLvLmo0C94T54Fr; ig_nrcb=1; wd=348x923",
    "Referer": "https://www.instagram.com/accounts/emailsignup/",
    "Referrer-Policy": "strict-origin-when-cross-origin"
  },
  "body": "code=370621&device_id=Z7CGBQAEAAHuxKLvLmo0C94T54Fr&email=dimat33456%40minduls.com",
  "method": "POST"
});

```
