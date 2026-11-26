# Running Bella Vista with no internet

**Status in this copy:** the local fonts are *prepared but not included*. `assets/fonts/` is empty and `css/fonts.css` has no font files until you run the one-time download below, so the site is **not** fully offline-ready until you do. The download itself could not be tested in the repair environment (no internet); only the script's conversion logic was tested (`php tests/fonts_tool_check.php`).

The site, API and database all run on your own computer (Apache + PHP + MariaDB in XAMPP). Only two things normally come from the internet. Both have an offline answer.

## 1. Fonts (Cormorant Garamond and Jost)
Run this **once, while online**, from the project folder:

```
C:\xampp\php\php.exe tools\download-fonts.php
```

It saves the font files into `assets/fonts/` and writes `css/fonts.css`. After that the site looks the same offline. Until you run it, `css/fonts.css` is empty and a browser without internet shows a fallback serif/sans font. When online, the pages still prefer Google Fonts, so nothing changes for visitors.

If the script says it cannot reach Google Fonts, check the connection and run it again. It changes nothing when it fails.

## 2. Map on the Contact page
The embedded Google Map cannot load offline. The page now always shows the address as text under the map, with an "Open in Google Maps" link when you are online. When the browser reports it is offline, the empty map box is not shown at all.

## Still needs the internet
- Google Maps on the Contact page (a text address is shown instead when offline).
- Sending real email (SMTP). Offline, the password-reset link is shown on the Forgot password page (`OFFLINE_RESET_ON_SCREEN` in `php/config.php`) and also written to `data/mail.log`. This is switched off automatically when `PRODUCTION` is true or an SMTP server is set. Turn it off yourself if other people can reach the site.
- The pages still contain the Google Fonts `<link>`; online it is used as a backup, offline it just fails and the local fonts are used.

## 3. Email
With no SMTP server, no email is sent. Password reset links and verification links are written to `data/mail.log` (development mode only). Open that file, copy the newest link and open it in the browser.

## Quick offline check
1. Start Apache and MySQL in the XAMPP Control Panel.
2. Turn off Wi-Fi / unplug the network.
3. Open `http://localhost/Bella-Vista-Oasis-Spa/` and click through Home, Services, Contact, Login.
4. Fonts should look right (after step 1) and the Contact page should show the address instead of an empty map.
