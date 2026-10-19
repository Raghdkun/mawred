# Phase 7b demo script: Sessions, check-in, certificates and training requests

About 15 minutes. Uses the demo company with the Phase 7a training demo (see [phase-7a.md](phase-7a.md)). Running the training demo seeder again adds the 7b data to an existing 7a demo:

```bash
php artisan db:seed --class="Database\Seeders\Demo\TrainingDemoSeeder"
```

It adds **Barista masterclass** with two sessions next week (four seats booked, on everyone's calendar), and certificates for the people who finished Customer service essentials. Keep a queue worker running (`composer dev`).

## 1. Sessions and the waitlist (D98, D107, D108)
1. As **trainer@mawred.test**, **Courses** → **Barista masterclass** → **Sessions** tab: each session with its seats booked and waiting. **Plan a session** sets the date and times (in the branch's timezone), on site or online, and the number of seats. It goes on your calendar.
2. As a learner on the course, the course page lists the sessions with seats left. **Book a seat** adds it to your calendar; when it's full, **Join the waitlist** shows your place in line. Giving up a seat moves the first person waiting up, with a notification and the calendar invite.
3. Sessions are alternatives: booking another one gives up the first. Completing the course needs the required modules and attending one session.

## 2. Check-in (D101, handoff 34)
1. As the trainer, open a session's **Roster** → **Show the check-in QR**. The code changes every 30 seconds; the count of people checked in updates as they scan.
2. On a phone signed in as a learner, scan the QR with the camera and tap **Check in** (from an hour before the session until it ends). Anyone enrolled can check in, booked or not.
3. The roster shows who checked in by QR and when; tick or untick people by hand.

## 3. Certificates (D103, D104, D110)
1. Completing a course issues a certificate: the course page and **My learning** show it with **Download** (A4 landscape PDF with the frame artwork, the holder, course, dates, serial and a QR). Arabic mirrors the text.
2. **Check online** (or scanning the QR) opens `/verify/{serial}` for anyone: name, course, dates and Valid / Expired / Revoked, nothing else.
3. HR or a trainer can **Revoke** one from the profile's **Training** tab; the check then shows Revoked.
4. `training:certificates` runs daily at 06:10: reminders 60, 30 and 7 days before expiry to the holder and their manager, and from 30 days out a renewal enrolment due on the expiry date.

## 4. Feedback (D109)
- After completing, the course page asks **How was this course?**: 1–5 stars and an optional comment. Trainers see the average and comments on the course's **People** page.

## 5. Training requests (D105)
1. As **employee@mawred.test**, **Requests → Training request** (course, provider, dates, cost). Once it's approved, it appears on **My learning** under **Outside training**; **Add the certificate** uploads it.
2. On the person's profile **Training** tab, a trainer or HR can choose a catalog course and **Enrol instead**.

## Checks
`composer test` (seats, waitlist and promotion, QR and manual check-in, completion with a session, feedback, certificates, the public check, revoking, reminders and renewal, training requests), `composer test:browser` (book and check in, plan a session and run check-in, the certificate check and records in EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep and the dark verification page), `pnpm test`.
