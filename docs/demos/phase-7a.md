# Phase 7a demo script: Training catalog, courses and learning

About 15 minutes. Uses the demo company (see [phase-1.md](phase-1.md)). On a company seeded before Phase 7, add the training demo:

```bash
php artisan db:seed --class="Database\Seeders\Demo\TrainingDemoSeeder"
```

It adds **trainer@mawred.test** (same `DEMO_PASSWORD`), four courses, a learning path, and learners at different stages. Food safety is required for everyone in the Dubai branch, so they're all enrolled with a due date. Keep a queue worker running (`composer dev`) so approvals and assignments move on.

## 1. The catalog (handoff 33)
1. As **employee@mawred.test**, open **Training**: course cards (three across on desktop, one on a phone) with category pills and search. Required courses show first, with a progress ring on courses you're on.
2. Open **Customer service essentials** and **Enrol**. It starts straight away.
3. Open **Leading a shift**. It needs approval, so **Ask to enrol** goes to your manager (D99). As **branch.manager@mawred.test**, **Approvals** shows the course and the learner; approve it.
4. **Learning paths** tab → **New store starter**: the stepper shows each course and where you are. **Join the path** enrols you in each course.

## 2. Working through a course (handoff 34)
1. Back on **Food safety basics** (assigned, due in 30 days), **Continue**. The outline sits beside the lesson (folded above it on a phone).
2. **Mark as done and continue** moves to the video, then the quiz.
3. The quiz: radio groups for one answer, checkboxes for several, options in the same order in both languages (D102). Fail it once to see the result screen with which questions were right and the attempts left; pass it to see the success ripple. Passing the last required module completes the course.
4. **My learning**: counts at the top (in progress, overdue, completed), then each course with its progress ring and due date.

## 3. Running the catalog (trainers)
1. As **trainer@mawred.test**, **Courses** (admin) lists every course with how many people are in progress, waiting and completed.
2. **New course**: details in English and Arabic, category, length, whether it needs approval, and the certificate's validity. Then **Modules**: add a lesson, video, file or quiz, reorder them, and write quiz questions with the right answers marked. **Publish** puts it in the catalog.
3. **Audience** makes a course required for branches, departments (with their sub-departments), positions or employment types, optionally only recent joiners, with days to complete (D100). Publishing assigns it; new joiners and people who move get it automatically, and `training:assign` runs nightly to catch anything missed.
4. **People** on a course: filter by status (including overdue), **Assign people** with a due date, give someone **another go** at a quiz, or take them off the course.
5. **Learning paths**: group published courses in order and publish the path.

## 4. Who sees what
- Everyone can browse the catalog and enrol themselves. Trainers and HR admins run courses and see everyone's progress. Branch and department managers see their people's training on the profile **Training** tab. Out-of-scope people answer 404.

## Checks
`composer test` (publishing rules, approval workflow, quiz scoring and attempts, audience assignment for joiners and movers, learning paths, scope leaks, API), `composer test:browser` (enrol and pass a quiz, build and publish a course, the training screens in EN + AR with axe; `SCREENSHOTS=1` for the 390/834/1440 sweep and the dark catalog), `pnpm test`.
