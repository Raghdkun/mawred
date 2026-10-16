# Phase 6a demo script: Requisitions, postings, careers and the pipeline

About 20 minutes. Uses the demo company (see [phase-1.md](phase-1.md)). On a company seeded before Phase 6, add the hiring demo:

```bash
php artisan db:seed --class="Database\Seeders\Demo\HiringDemoSeeder"
```

It adds **recruiter@mawred.test** (same `DEMO_PASSWORD`), two approved requisitions in Dubai with live postings and seven applicants, and one requisition still awaiting approval. Keep a queue worker running (`composer dev`) so approvals move on.

## 1. Raising a requisition (D84)
1. As **branch.manager@mawred.test**, **Hiring → New requisition**. Fill in the Job requisition form (title, branch, number of hires, reason, salary range, why) and **Send for approval**.
2. It appears under **Awaiting approval**. The approvers (the manager's manager, then HR) see it in **Approvals** like any request. The drawer shows the approval timeline.
3. Once approved, the requisition opens with the default pipeline, and the person who asked joins its hiring team as hiring manager.

## 2. Postings and the careers site (D81, D87)
1. As **recruiter@mawred.test**, open a requisition → **New posting**. Write the advert in English and Arabic, pick the application form, add knock-outs (for example "I need sponsorship" on *right to work*, or fewer than 2 years of experience) and **Publish**.
2. Open **/careers** (or the **Link to share**): job cards with search and filters, the company's logo in the header, Mawred only as "powered by". `?lang=ar` serves the Arabic page; share previews use the per-language OG image, and search engines get JSON-LD and `/careers/sitemap.xml`.
3. Apply as a visitor: fill the form, attach a CV, accept the privacy notice (optionally join the talent pool) and send. The proof-of-work check runs in the background.

## 3. The pipeline (handoff 29 ★)
1. **Open the board**: stage columns with counts, cards with days in stage, source, tags, **Screening** (knock-out) and **Possible duplicate** flags.
2. Drag a card to another stage, use the keyboard (focus the handle, Space, arrows, Space), or **Move to…**. Select several and **Reject** with a reason; **Rejected** lists them and can put one back.
3. **⋯ → Stage settings** renames, reorders, adds and removes stages (applied first, hired last; occupied stages stay). **Hiring settings** holds the default pipeline and the careers texts.
4. **Add candidate** adds someone who applied another way (manual or referral) with the default application form.
5. On a phone the board shows one stage at a time with a stage switcher.

## 4. Candidates (handoff 30)
1. Click a card: the CV sits at the reading start with the details beside it (tabs on a phone), then the answers and the history with notes.
2. Tag the candidate, toggle the talent pool, or **Add to another job**.
3. **Candidates → Possible duplicates**: "Layla Mansoori" shares a phone with "Layla Al-Mansoori". Merging is refused while both are on the same job; reject one application first, or mark them as different people.

## 5. Who sees what (D82)
- Recruiters and HR admins see every requisition. Branch and department managers see their own branch or department; anyone on a hiring team sees that requisition's candidates. Out-of-scope pages answer 404.

## Checks
`composer test` (requisition approval, knock-outs, duplicates, stage rules, scope leaks, careers SEO, apply with ALTCHA), `composer test:browser` (move a candidate, apply on the careers site, hiring screens in EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep and the dark board), `pnpm test`.
