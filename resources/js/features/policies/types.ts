import type { FormDataConvertible } from '@inertiajs/core';

export type PolicyKey = App.Domain.Policies.Data.PolicyKeyData;
export type PolicySet = App.Domain.Policies.Data.PolicySetData;
export type PolicyAssignment = App.Domain.Policies.Data.PolicyAssignmentData;
export type PolicySetValue = App.Domain.Policies.Data.PolicySetValueData;
export type ResolvedValue = App.Domain.Policies.Data.ResolvedValueData;
export type ChainLink = App.Domain.Policies.Data.ChainLinkData;
export type ImpactPreview = App.Domain.Policies.Data.ImpactPreviewData;
export type LeaveType = App.Domain.Policies.Data.LeaveTypeData;
export type HolidayCalendar = App.Domain.Policies.Data.HolidayCalendarData;
export type HolidayRule = App.Domain.Policies.Data.HolidayRuleData;
export type HolidayDay = App.Domain.Policies.Data.HolidayDayData;

/** A policy value as stored: fields keyed by name. */
export type PolicyValue = Record<string, FormDataConvertible>;

export type PolicyLevel =
    'global' | 'branch' | 'department' | 'position' | 'grade' | 'employment_type' | 'employee';
