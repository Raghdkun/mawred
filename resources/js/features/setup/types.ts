export type StepKey = App.Domain.Setup.Enums.SetupStep;
export type StepStatus = App.Domain.Setup.Enums.StepStatus;

export type Step = {
    key: StepKey;
    number: number;
    status: StepStatus;
    required: boolean;
    availableInPhase: number;
};
