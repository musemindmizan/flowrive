/**
 * Shapes the FlowRive controllers hand to Inertia.
 *
 * These mirror what DashboardController returns rather than the database
 * rows — the page gets exactly what it renders, already formatted.
 */

export type Tone =
    | 'brand'
    | 'success'
    | 'warning'
    | 'danger'
    | 'info'
    | 'neutral';

export type Person = {
    id: number;
    name: string;
};

export type Workspace = {
    id: number;
    name: string;
};

export type Greeting = {
    name: string;
    part: 'morning' | 'afternoon' | 'evening';
    message: string;
};

export type Stat = {
    key: string;
    label: string;
    value: number;
    icon: string;
    tone: Tone;
    meta?: string | null;
    badge?: string | null;
    badgeTone?: Tone | null;
};

export type DashboardProject = {
    id: number;
    key: string;
    name: string;
    status: string;
    statusLabel: string;
    statusTone: Tone;
    progress: number;
    tasksTotal: number;
    tasksCompleted: number;
    deadline: string | null;
    members: Person[];
};

export type DashboardTask = {
    id: number;
    key: string;
    title: string;
    priority: string;
    priorityLabel: string;
    priorityTone: Tone;
    priorityIcon: string;
    dueDate: string | null;
    project: { id: number; name: string };
};

export type Deadline = {
    type: 'task' | 'project';
    id: string;
    title: string;
    meta: string;
    date: string;
};

export type ActivityEntry = {
    id: number;
    verb: string;
    actor: Person | null;
    target: string | null;
    project: string | null;
    meta: string | null;
    at: string | null;
};

export type WorkloadRow = {
    id: number;
    name: string;
    open: number;
    percent: number;
    tone: Tone;
};

export type DashboardProps = {
    workspace: Workspace | null;
    greeting: Greeting;
    stats?: Stat[];
    projects?: DashboardProject[];
    myTasks?: DashboardTask[];
    deadlines?: Deadline[];
    activity?: ActivityEntry[];
    workload?: WorkloadRow[];
};

/* ------------------------------------------------------------- projects */

export type ProjectCard = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    status: string;
    statusLabel: string;
    statusTone: Tone;
    priority: string;
    priorityLabel: string;
    priorityTone: Tone;
    priorityIcon: string;
    progress: number;
    tasksTotal: number;
    tasksCompleted: number;
    files: number;
    deadline: string | null;
    lead: Person | null;
    members: Person[];
};

export type ProjectFilters = {
    search: string;
    status: string[];
    sort: 'deadline' | 'name' | 'progress' | 'priority';
};

export type ProjectsPageProps = {
    projects: ProjectCard[];
    filters: ProjectFilters;
    statusOptions: { value: string; label: string }[];
    summary: { projects: number; active: number; tasks: number };
};

/* ---------------------------------------------------------------- board */

export type BoardLabel = {
    id: number;
    name: string;
    color: string;
};

export type BoardTask = {
    id: number;
    key: string;
    title: string;
    priority: string;
    priorityLabel: string;
    priorityTone: Tone;
    priorityIcon: string;
    dueDate: string | null;
    assignee: Person | null;
    labels: BoardLabel[];
    comments: number;
    attachments: number;
    subtasksTotal: number;
    subtasksCompleted: number;
};

export type BoardColumn = {
    id: number;
    name: string;
    category: string;
    tone: Tone;
    wipLimit: number | null;
    tasks: BoardTask[];
};

export type BoardProject = {
    id: number;
    key: string;
    name: string;
    status: string;
    statusLabel: string;
    statusTone: Tone;
    progress: number;
    tasksTotal: number;
    tasksCompleted: number;
    deadline: string | null;
    members: Person[];
};

export type BoardPageProps = {
    project: BoardProject;
    columns: BoardColumn[];
    canContribute: boolean;
};

/* ---------------------------------------------------------------- tasks */

export type TaskRow = {
    id: number;
    key: string;
    title: string;
    priority: string;
    priorityLabel: string;
    priorityTone: Tone;
    priorityIcon: string;
    status: string;
    statusTone: Tone;
    isComplete: boolean;
    dueDate: string | null;
    project: { id: number; key: string; name: string };
    assignee: Person | null;
    labels: string[];
    comments: number;
    attachments: number;
};

export type TaskScope = 'all' | 'mine' | 'overdue' | 'done';

export type TaskFilters = {
    search: string;
    scope: TaskScope;
    project: number | null;
    status: string[];
    priority: string[];
    sort: 'due' | 'title' | 'priority' | 'created';
    direction: 'asc' | 'desc';
};

export type TasksPageProps = {
    tasks: TaskRow[];
    meta: {
        from: number | null;
        to: number | null;
        total: number;
        currentPage: number;
        lastPage: number;
    };
    filters: TaskFilters;
    counts: Record<TaskScope, number>;
    projectOptions: { id: number; key: string; name: string }[];
    statusOptions: { value: string; label: string }[];
    priorityOptions: { value: string; label: string }[];
};

/* --------------------------------------------------------- task detail */

export type TaskComment = {
    id: number;
    body: string;
    author: { id: number; name: string; title: string | null } | null;
    at: string | null;
    isEdited: boolean;
    isMine: boolean;
    reactions: number;
    hasReacted: boolean;
    replies?: TaskComment[];
};

export type TaskSubtask = {
    id: number;
    title: string;
    isCompleted: boolean;
};

export type TaskAttachment = {
    id: number;
    name: string;
    size: string;
    isImage: boolean;
    uploadedBy: string;
    uploadedAt: string | null;
};

export type TaskHistoryEntry = {
    id: number;
    verb: string;
    actor: string | null;
    meta: string | null;
    at: string | null;
};

export type TaskDetail = {
    id: number;
    key: string;
    title: string;
    description: string | null;
    priority: string;
    priorityLabel: string;
    priorityTone: Tone;
    priorityIcon: string;
    status: string;
    statusTone: Tone;
    isComplete: boolean;
    startDate: string | null;
    dueDate: string | null;
    estimateHours: string | null;
    createdAt: string | null;
    updatedAt: string | null;
    project: { id: number; key: string; name: string; progress: number };
    assignee: { id: number; name: string; title: string | null } | null;
    reporter: { id: number; name: string; title: string | null } | null;
    labels: { id: number; name: string }[];
    watchers: Person[];
    isWatching: boolean;
};

export type TaskShowProps = {
    task: TaskDetail;
    subtasks: TaskSubtask[];
    attachments: TaskAttachment[];
    comments: TaskComment[];
    history: TaskHistoryEntry[];
    canContribute: boolean;
};

/* ----------------------------------------------------------------- team */

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    title: string | null;
    role: string;
    roleLabel: string;
    status: string;
    statusLabel: string;
    statusTone: Tone;
    presence: 'online' | 'away' | 'offline';
    joined: string | null;
    projects: number;
    openTasks: number;
    isYou: boolean;
};

export type PendingInvitation = {
    id: number;
    email: string;
    role: string;
    invitedBy: string;
    sentAt: string | null;
    expiresAt: string;
};

export type TeamPageProps = {
    members: TeamMember[];
    filters: { search: string; role: string | null; status: string | null };
    roleCounts: Record<string, number>;
    invitations: PendingInvitation[];
    roleOptions: { value: string; label: string; description: string }[];
    canInvite: boolean;
};

export type MemberPageProps = {
    member: {
        id: number;
        name: string;
        email: string;
        title: string | null;
        phone: string | null;
        bio: string | null;
        department: string | null;
        timezone: string;
        presence: 'online' | 'away' | 'offline';
        role: string;
        status: string;
        statusLabel: string;
        statusTone: Tone;
        joined: string | null;
    };
    stats: {
        openTasks: number;
        completed: number;
        projects: number;
        overdue: number;
    };
    projects: {
        id: number;
        key: string;
        name: string;
        progress: number;
        statusLabel: string;
        statusTone: Tone;
    }[];
    tasks: {
        id: number;
        key: string;
        title: string;
        status: string;
        statusTone: Tone;
        priorityLabel: string;
        priorityTone: Tone;
        priorityIcon: string;
        dueDate: string | null;
        project: string;
    }[];
};

/* -------------------------------------------------------- notifications */

export type NotificationItem = {
    id: string;
    category: string;
    title: string;
    body: string | null;
    href: string | null;
    actor: string | null;
    isRead: boolean;
    at: string | null;
};

export type NotificationsPageProps = {
    notifications: NotificationItem[];
    filters: { category: string | null; unread: boolean };
    counts: Record<string, number>;
    categories: { value: string; label: string; icon: string }[];
};
