# ADR-0005: RBAC + data scopes + field permissions via a central ScopeResolver

- Status: Proposed — anchors confirmed (D5); optional user↔employee link (D6); primary + secondary assignments (D25)
- Date: 2026-10-13

## Context
Permissions take the form `module.action` and combine with a data scope: own, direct reports, team tree, department, branch, all. Lists, reports, search, exports, broadcast channels and API endpoints must never leak out-of-scope data. spatie/laravel-permission models role↔permission, but it has no notion of scope.

## Decision
- spatie/laravel-permission stores roles, permissions and user↔role links (guard `web`; API tokens act as the same user, with Sanctum abilities as an extra ceiling).
- `role_permission_scopes (role_id, permission_id, scope enum)` stores the scope for each grant. The scope values stay `own | direct_reports | team_tree | department | branch | all`. The matrix UI (screen 38) shows **None / Own / Team / Department / Branch / All**, where "Team" expands to *Direct reports* or *Whole team tree* (default tree) and "None" means no grant (D29). The matrix UI edits both tables together through one Action.
- `role_assignment_anchors (model_has_roles key, anchor_type branch|department, anchor_id)` holds optional anchors. A user's effective scope for a permission is the **union** over their roles of `(scope, anchors or own placement)`.
- `Access\ScopeResolver` provides:
  - `constraint(User, 'leave.view'): ScopeConstraint`, which compiles to a WHERE on the subject employee (`employee_id = me`, `manager_id = me`, closure-table `EXISTS`, `department path <@ ltree`, `branch_id IN (...)`, or no constraint);
  - `allows(User, 'leave.approve', Model $record): bool`.
- Users without an employee record (D6) cannot hold `own`, `direct_reports` or `team_tree` grants. The matrix UI blocks this, and they must use anchors or the hiring `assigned` scope.
- Scope placement is the employee's **primary** assignment. Secondary assignments (D25) add `branch`/`department` visibility for scheduling and attendance permissions only.
- Every scoped model implements `ScopeSubject` and declares how it reaches the subject employee's **current** placement (e.g. `LeaveRequest → requester`). Candidates use a separate hiring scope (`assigned`, `job`, `all`).
- Effective scope is cached per user per request and in Redis. The cache is busted by `RolesChanged`, `AssignmentChanged` and `ReportingLineChanged` events.
- Policies call the resolver. `ScopedQuery` applies the constraint, and an arch test requires every `Queries\*` to extend it.
- **Field permissions**: `field_permissions (role_id, field_key, access none|read|write)` cover built-in sensitive fields and custom fields. Data objects mark fields with `#[Sensitive('employee.salary')]`. The transformer omits fields the user can't read and logs `sensitive.read` (deduplicated per request).
- **2FA by role**: a role flag `requires_2fa`. The `Ensure2FA` middleware redirects to enrolment.
- **Impersonation**: Super Admin only, never of another Super Admin, start and stop audited, persistent banner, and every audit entry carries `impersonator_id`.
- **Arch test**: every route except an allow-list of public ones has a policy/`can` middleware or an `authorize()` call. A route-introspection test fails when a controller method has no authorization.
- **Scope-leak test pattern**: a shared dataset of (role, scope, fixture org) runs against every list, search, report and export endpoint.

## Consequences
- + One choke point for data visibility, and it can be tested exhaustively.
- − Custom tables next to spatie's need care in role cloning/deletion Actions. Package helpers like `givePermissionTo` can't set a scope, so a wrapper Action is mandatory.
