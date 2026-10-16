# Phase 6b demo script: Interviews, scorecards, offers and the hire

About 20 minutes. Uses the demo company with the Phase 6a hiring demo (see [phase-6a.md](phase-6a.md)). Running the hiring demo seeder again adds the 6b data to an existing 6a demo:

```bash
php artisan db:seed --class="Database\Seeders\Demo\HiringDemoSeeder"
```

It adds Layla's first interview from yesterday (the branch manager's scorecard is in; the second panelist's is due), a phone screen waiting for Priya to book, and Omar's offer, approved by the hiring manager and HR and sent to him. Candidate emails go to Mailpit (http://localhost:8025). Keep a queue worker running (`composer dev`).

## 1. Interviews and self-booking (D89, D92)
1. As **recruiter@mawred.test**, open a candidate → **Plan interview**. Pick the panel (any colleague), the length and the place, then either **send a booking link** or set the time yourself.
2. Open the booking link from Mailpit (or copy it from the interview card): the candidate sees only times when the whole panel is free over the next two weeks, in the branch's timezone. They pick one and the panel gets the invitation with `.ics`. If someone books that time meanwhile, the page asks for another.
3. The interview shows on everyone's **Calendar**. Panelists who aren't on the hiring team see this candidate only.

## 2. Scorecards (D82, D90)
1. As a panelist, **My interviews** lists your interviews. "Scorecard to do" appears once the interview has started, and the sidebar badge counts them.
2. Rate each criterion from 1 to 4, recommend, comment, then **Submit**. You can **Save draft** first. Others' scorecards stay hidden until yours is in; the hiring team and HR see all of them, and the board card shows the average.
3. **Board → ⋯ → Scorecard criteria** edits the criteria for this job. **Hiring settings** holds the defaults new requisitions copy.

## 3. Stage automations and messages (D93, D94)
1. **Board → ⋯ → Stage automations**: per stage, a bilingual message to the candidate, plus on interview stages a booking invite with a set panel. Move a card into that stage and the message goes out in the candidate's language.
2. On the profile, **Messages** writes to the candidate by email or WhatsApp. WhatsApp replies from their number show up in the same log. Rejecting can send the thank-you message from Hiring settings.

## 4. Offers (D83, D91, D95)
1. On a candidate in **Offer**, choose **Draft an offer**: the job title, start date, salary, branch and manager. The letter fills in from the template in the candidate's language; edit it, then **Preview letter** (PDF).
2. **Send for approval**: the hiring manager, then HR, see it in **Approvals** with the offer's facts and the PDF.
3. Once approved, **Send** by email or WhatsApp. The candidate opens the signed link, reads the letter, types their full name, signs on the pad and accepts, or declines with a reason. The signed PDF (name, signature, time) is attached to the offer.

## 5. Hire (D85)
1. After the candidate accepts, **Hire** (HR) opens prefilled from the offer. Confirm it: a **Pending** employee is created with the starting salary and onboarding begins, the application moves to **Hired**, and the requisition fills at headcount.

## 6. Cognito-backed postings (D81, D97)
1. **Integrations → Cognito → New source**, leaving the request type empty. In a posting, **Where candidates apply** → pick the source.
2. On the source page, map the Cognito fields onto the posting's application form (name, email, phone, CV…). The careers page now links to the hosted form, and each entry creates or refreshes the application (source "Cognito form"), with the CV downloaded.

## 7. Retention (D86, D96)
- **Delete their details** on a profile anonymises the candidate now. Candidates can do the same from the link in every email. `hiring:purge` runs nightly at 03:10 for candidates past the retention period. Hired candidates are never purged.

## Checks
`composer test` (booking with slot re-check, forged links, blind scorecards, automations, comms log, offer approval chain, signing, decline, hire, anonymise, purge, Cognito postings, scope leaks), `composer test:browser` (book an interview, fill a scorecard, sign the offer, confirm the hire, EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep and the dark offer page), `pnpm test`.
