import {
    BookOpenText,
    Compass,
    Cpu,
    FileText,
    HardHat,
    Lightbulb,
    ListChecks,
    type LucideIcon,
    Paperclip,
    PlayCircle,
    ShieldCheck,
    Users,
} from 'lucide-react';

export const CATEGORY_ICONS: Record<string, LucideIcon> = {
    compliance: ShieldCheck,
    safety: HardHat,
    onboarding: Compass,
    skills: Lightbulb,
    leadership: Users,
    technical: Cpu,
};

export const MODULE_ICONS: Record<string, LucideIcon> = {
    text: FileText,
    video: PlayCircle,
    file: Paperclip,
    quiz: ListChecks,
};

export const FallbackIcon = BookOpenText;
