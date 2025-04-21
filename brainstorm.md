## Name Generator Brainstorm
Generate me nodejs cli script to generate first name, last name, gender, and password
It will saved to CSV, and add some optional question

- Generate random name lookup from this method
```php
    public function generateRandomName()
    {
        // Simple name generation logic - can be expanded
        $response = Http::get('http://ninjaname.horseridersupply.com/indonesian_name.php', [
            'number_generate' => '30',
            'gender_type' => 'female',
        ]);

        if ($response->successful()) {
            preg_match_all('~(&bull; (.*?)<br/>&bull; )~', $response->body(), $matches);
            if (isset($matches[2]) && ! empty($matches[2])) {
                return $matches[2][array_rand($matches[2])];
            }
        }

        throw new \Exception('not user');
    }

```
    - randomize between male and female
    - return the gender
    - generate password from combining name with extra number up to 4 digit in the end
- After printing to the cli all of the result
- save to csv(add notification to cli)
- and ask question with "Save to Redispo?"
- if yes make dummy call api(we will include this later), and if success update the csv

---

FLOW
- attempt (3x)
- check_age_eligibility
- send_verify_email
- check_confirmation_code
- web_create_ajax

fetch("https://www.instagram.com/api/v1/web/accounts/web_create_ajax/attempt/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.8",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Brave\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Brave\";v=\"133.0.0.0\", \"Chromium\";v=\"133.0.0.0\"",
    "sec-ch-ua-mobile": "?1",
    "sec-ch-ua-model": "\"SM-G955U\"",
    "sec-ch-ua-platform": "\"Android\"",
    "sec-ch-ua-platform-version": "\"8.0.0\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "sec-gpc": "1",
    "x-asbd-id": "359341",
    "x-csrftoken": "Yxa2iWRaeVC_SM-AND1NpO",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1022052427",
    "x-requested-with": "XMLHttpRequest",
    "x-web-session-id": "wl8egq:2p976y:a9osxn"
  },
  "referrer": "https://www.instagram.com/accounts/emailsignup/",
  "referrerPolicy": "strict-origin-when-cross-origin",
  "body": "enc_password=%23PWD_INSTAGRAM_BROWSER%3A10%3A1745053814%3AAblQAPBWJEX5uVlYGrnOEULPeWSpCENntNA48PId4XR4xw1z%2BiDK0zpLdlzlm11fXLVmFsJJPVc33ip%2BJAqed4XszF77Esr7VamQmcwMfLycaPJF4HdYmdrNXKJX7uaB2Y7K97UVHcmPiNRnotH4&email=3036presidential%40ptct.net&failed_birthday_year_count=%7B%7D&first_name=Marasta+Predential&username=marastapredential&client_id=aANoKQAEAAEuSzCIYBuSV0AWB9he&seamless_login_enabled=1&opt_into_one_tap=false&use_new_suggested_user_name=true&jazoest=21810",
  "method": "POST",
  "mode": "cors",
  "credentials": "include"
});

---
fetch("https://www.instagram.com/api/v1/web/consent/check_age_eligibility/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.8",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Brave\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Brave\";v=\"133.0.0.0\", \"Chromium\";v=\"133.0.0.0\"",
    "sec-ch-ua-mobile": "?1",
    "sec-ch-ua-model": "\"SM-G955U\"",
    "sec-ch-ua-platform": "\"Android\"",
    "sec-ch-ua-platform-version": "\"8.0.0\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "sec-gpc": "1",
    "x-asbd-id": "359341",
    "x-csrftoken": "Yxa2iWRaeVC_SM-AND1NpO",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1022052427",
    "x-requested-with": "XMLHttpRequest",
    "x-web-session-id": "wl8egq:2p976y:a9osxn"
  },
  "referrer": "https://www.instagram.com/accounts/emailsignup/",
  "referrerPolicy": "strict-origin-when-cross-origin",
  "body": "day=6&month=9&year=1983&jazoest=21810",
  "method": "POST",
  "mode": "cors",
  "credentials": "include"
});

---
fetch("https://www.instagram.com/api/v1/accounts/check_confirmation_code/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.5",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Brave\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Brave\";v=\"133.0.0.0\", \"Chromium\";v=\"133.0.0.0\"",
    "sec-ch-ua-mobile": "?1",
    "sec-ch-ua-model": "\"SM-G955U\"",
    "sec-ch-ua-platform": "\"Android\"",
    "sec-ch-ua-platform-version": "\"8.0.0\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "sec-gpc": "1",
    "x-asbd-id": "359341",
    "x-csrftoken": "n0LXWVm6qh2l7Dad_oLfzb",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1022052427",
    "x-requested-with": "XMLHttpRequest",
    "x-web-device-id": "7D7815DC-3E52-49E3-8632-5D3BB64DDEB2",
    "x-web-session-id": "p20m3h:cw999t:tgi2a0",
    "cookie": "csrftoken=n0LXWVm6qh2l7Dad_oLfzb; datr=5WkDaLhN33Vq1VzaV8i6ZDzC; ig_did=7D7815DC-3E52-49E3-8632-5D3BB64DDEB2; mid=aANp5QAEAAHz3-BYltNDEDrVHGKG; ig_nrcb=1; wd=360x740",
    "Referer": "https://www.instagram.com/accounts/emailsignup/",
    "Referrer-Policy": "strict-origin-when-cross-origin"
  },
  "body": "code=837465&device_id=aANp5QAEAAHz3-BYltNDEDrVHGKG&email=pieretteamethyst%40ptct.net&jazoest=21957",
  "method": "POST"
});

---


fetch("https://www.instagram.com/api/v1/web/accounts/web_create_ajax/", {
  "headers": {
    "accept": "*/*",
    "accept-language": "en-US,en;q=0.5",
    "content-type": "application/x-www-form-urlencoded",
    "priority": "u=1, i",
    "sec-ch-ua": "\"Not(A:Brand\";v=\"99\", \"Brave\";v=\"133\", \"Chromium\";v=\"133\"",
    "sec-ch-ua-full-version-list": "\"Not(A:Brand\";v=\"99.0.0.0\", \"Brave\";v=\"133.0.0.0\", \"Chromium\";v=\"133.0.0.0\"",
    "sec-ch-ua-mobile": "?1",
    "sec-ch-ua-model": "\"SM-G955U\"",
    "sec-ch-ua-platform": "\"Android\"",
    "sec-ch-ua-platform-version": "\"8.0.0\"",
    "sec-fetch-dest": "empty",
    "sec-fetch-mode": "cors",
    "sec-fetch-site": "same-origin",
    "sec-gpc": "1",
    "x-asbd-id": "359341",
    "x-csrftoken": "n0LXWVm6qh2l7Dad_oLfzb",
    "x-ig-app-id": "936619743392459",
    "x-ig-www-claim": "0",
    "x-instagram-ajax": "1022052427",
    "x-requested-with": "XMLHttpRequest",
    "x-web-device-id": "7D7815DC-3E52-49E3-8632-5D3BB64DDEB2",
    "x-web-session-id": "p20m3h:cw999t:tgi2a0",
    "cookie": "csrftoken=n0LXWVm6qh2l7Dad_oLfzb; datr=5WkDaLhN33Vq1VzaV8i6ZDzC; ig_did=7D7815DC-3E52-49E3-8632-5D3BB64DDEB2; mid=aANp5QAEAAHz3-BYltNDEDrVHGKG; ig_nrcb=1; wd=360x740",
    "Referer": "https://www.instagram.com/accounts/emailsignup/",
    "Referrer-Policy": "strict-origin-when-cross-origin"
  },
  "body": "enc_password=%23PWD_INSTAGRAM_BROWSER%3A10%3A1745054396%3AAblQAF7ov7In4Iqkr05OFPtdNwzO9C8uMdjLUfy13t3CnMd%2F1mLyMvs0pkyBakCrHCN%2FMYE5pO%2BTDFyHJJvOOH488szVc2M5GMQ0NpQ%2FuliJhliwddJwmb7x6%2F4FQkkIvv6fy5qv%2FMb7q2VVPj5Y&day=2&email=pieretteamethyst%40ptct.net&failed_birthday_year_count=%7B%7D&first_name=Pierre+Muhammad&month=3&username=pieretteamethyst&year=1972&client_id=aANp5QAEAAHz3-BYltNDEDrVHGKG&seamless_login_enabled=1&tos_version=row&force_sign_up_code=9YNyP67E&extra_session_id=p20m3h%3Acw999t%3Atgi2a0&jazoest=21957",
  "method": "POST"
});
