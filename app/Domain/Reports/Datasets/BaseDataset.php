<?php

declare(strict_types=1);

namespace App\Domain\Reports\Datasets;

use App\Domain\Access\Enums\FieldAccess;
use App\Domain\Access\Models\User;
use App\Domain\Access\Support\ScopeResolver;
use App\Domain\Access\Support\SubjectBinding;
use App\Domain\Reports\Enums\ReportCategory;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A source the custom builder reads from (D121): its fields, and a base query scoped through the
 * ScopeResolver with the module permission, exactly like the prebuilt report on the same data.
 *
 * @template TModel of Model
 */
abstract class BaseDataset
{
    public function __construct(protected readonly ScopeResolver $resolver) {}

    abstract public function key(): string;

    abstract public function category(): ReportCategory;

    /** @return array{en: string, ar: string} */
    abstract public function title(): array;

    /** @return array{en: string, ar: string} */
    abstract public function description(): array;

    /** The module permission whose scope limits the rows. */
    abstract public function permission(): string;

    /** @return list<DatasetField> */
    abstract public function fields(): array;

    /** The date field the viewer's period applies to, if any. */
    abstract public function dateField(): ?string;

    /**
     * The rows before scoping, with the joins the fields read from.
     *
     * @return Builder<TModel>
     */
    abstract protected function base(): Builder;

    abstract protected function subject(): SubjectBinding;

    /** The branch column for the viewer's branch filter. */
    public function branchColumn(): ?string
    {
        return 'employees.current_branch_id';
    }

    /** The department column for the viewer's department filter (sub-departments included). */
    public function departmentColumn(): ?string
    {
        return 'employees.current_department_id';
    }

    public function field(string $key): ?DatasetField
    {
        foreach ($this->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * The rows this person may see.
     *
     * @return Builder<TModel>
     */
    public function query(User $user): Builder
    {
        return $this->resolver->apply($this->base(), $user, $this->permission(), $this->subject());
    }

    /** A sensitive field shows only to people with its field permission. */
    public function visible(User $user, DatasetField $field): bool
    {
        return $field->sensitive === null || $this->resolver->fieldAccess($user, $field->sensitive) instanceof FieldAccess;
    }

    public function canUse(User $user): bool
    {
        return $this->resolver->can($user, $this->permission());
    }

    /**
     * A translatable jsonb column in the reader's language, falling back to English.
     *
     * @param  literal-string  $column
     * @return Closure(string): literal-string
     */
    protected static function translated(string $column): Closure
    {
        return fn (string $locale): string => $locale === 'ar'
            ? "coalesce(nullif({$column}->>'ar', ''), {$column}->>'en')"
            : "{$column}->>'en'";
    }

    /**
     * Someone's name the way the profile shows it.
     *
     * @param  literal-string  $table
     * @return Closure(string): literal-string
     */
    protected static function personName(string $table = 'employees'): Closure
    {
        return fn (string $locale): string => $locale === 'ar'
            ? "coalesce(nullif({$table}.preferred_name, ''), nullif({$table}.name_ar, ''), {$table}.name_en)"
            : "coalesce(nullif({$table}.preferred_name, ''), {$table}.name_en)";
    }

    /**
     * A timestamp as a calendar date. Instants are UTC and so is the app clock (ADR-0004), the
     * same day boundary the prebuilt reports use (`Instants`).
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    protected static function day(string $column): string
    {
        return "({$column} at time zone 'UTC')::date";
    }

    /**
     * @param  array<string, array{0: string, 1: string}>  $labels  value => [en, ar]
     * @return list<array{value: string, label: array{en: string, ar: string}}>
     */
    protected static function options(array $labels): array
    {
        $options = [];

        foreach ($labels as $value => [$en, $ar]) {
            $options[] = ['value' => (string) $value, 'label' => ['en' => $en, 'ar' => $ar]];
        }

        return $options;
    }

    /**
     * The shared people fields every person-based dataset offers.
     *
     * @return list<DatasetField>
     */
    protected static function personFields(): array
    {
        return [
            DatasetField::text('employee_number', 'Employee no.', 'الرقم الوظيفي', 'employees.employee_number', groupable: false),
            DatasetField::text('employee_name', 'Name', 'الاسم', self::personName(), groupable: false),
            DatasetField::text('branch', 'Branch', 'الفرع', self::translated('rb.name')),
            DatasetField::text('department', 'Department', 'القسم', self::translated('rd.name')),
        ];
    }
}
