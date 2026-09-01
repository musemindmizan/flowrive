import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { MessageSquare, Send, ThumbsUp, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { UserAvatar } from '@/components/user-avatar';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    destroy as destroyComment,
    store as storeComment,
    react as reactToComment,
} from '@/routes/comments';
import type { Person, TaskComment } from '@/types';

export function CommentThread({
    taskId,
    comments,
    me,
    canContribute,
}: {
    taskId: number;
    comments: TaskComment[];
    me: Person;
    canContribute: boolean;
}) {
    const [replyTo, setReplyTo] = useState<number | null>(null);

    return (
        <div>
            <ul className="divide-border divide-y">
                {comments.map((comment) => (
                    <li key={comment.id} className="p-4 sm:p-5">
                        <Comment
                            comment={comment}
                            canContribute={canContribute}
                            onReply={() => setReplyTo(comment.id)}
                        />

                        {comment.replies && comment.replies.length > 0 && (
                            <ul className="border-border mt-4 space-y-4 border-l pl-4 sm:pl-6">
                                {comment.replies.map((reply) => (
                                    <li key={reply.id}>
                                        <Comment
                                            comment={reply}
                                            canContribute={canContribute}
                                            onReply={() =>
                                                setReplyTo(comment.id)
                                            }
                                        />
                                    </li>
                                ))}
                            </ul>
                        )}

                        {replyTo === comment.id && canContribute && (
                            <div className="mt-4 pl-4 sm:pl-6">
                                <CommentForm
                                    taskId={taskId}
                                    me={me}
                                    parentId={comment.id}
                                    placeholder="Write a reply…"
                                    onDone={() => setReplyTo(null)}
                                    onCancel={() => setReplyTo(null)}
                                />
                            </div>
                        )}
                    </li>
                ))}
            </ul>

            {comments.length === 0 && (
                <p className="text-muted-foreground px-5 py-10 text-center text-[13px]">
                    No comments yet. Start the conversation.
                </p>
            )}

            {canContribute ? (
                <div className="border-border border-t p-4 sm:p-5">
                    <CommentForm taskId={taskId} me={me} />
                </div>
            ) : (
                <p className="border-border text-muted-foreground border-t px-5 py-4 text-center text-xs">
                    You have view-only access to this project.
                </p>
            )}
        </div>
    );
}

function Comment({
    comment,
    canContribute,
    onReply,
}: {
    comment: TaskComment;
    canContribute: boolean;
    onReply: () => void;
}) {
    return (
        <div className="flex gap-3">
            {comment.author && (
                <UserAvatar person={comment.author} size="sm" />
            )}

            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                    <span className="text-foreground text-[13px] font-semibold">
                        {comment.author?.name ?? 'Unknown'}
                    </span>
                    {comment.author?.title && (
                        <span className="bg-neutral-soft text-neutral-ink rounded-full px-2 text-[11px] font-medium">
                            {comment.author.title}
                        </span>
                    )}
                    <span className="text-muted-foreground text-[11px]">
                        {comment.at}
                        {comment.isEdited && ' · edited'}
                    </span>

                    {comment.isMine && (
                        <button
                            type="button"
                            onClick={() =>
                                router.delete(
                                    destroyComment({ comment: comment.id }).url,
                                    {
                                        preserveScroll: true,
                                        onSuccess: () =>
                                            toast.success('Comment deleted.'),
                                    },
                                )
                            }
                            className="text-muted-foreground hover:text-danger-ink ml-auto rounded p-1"
                            aria-label="Delete comment"
                        >
                            <Trash2 className="size-3.5" aria-hidden />
                        </button>
                    )}
                </div>

                <p className="text-ink-2 mt-1.5 text-[13px] leading-6 whitespace-pre-line">
                    {comment.body}
                </p>

                <div className="mt-2 flex items-center gap-1.5">
                    <button
                        type="button"
                        disabled={!canContribute}
                        aria-pressed={comment.hasReacted}
                        aria-label={
                            comment.hasReacted
                                ? 'Remove your reaction'
                                : 'React with a thumbs up'
                        }
                        onClick={() =>
                            router.post(
                                reactToComment({ comment: comment.id }).url,
                                {},
                                { preserveScroll: true },
                            )
                        }
                        className={cn(
                            'inline-flex h-5.5 items-center gap-1 rounded-full px-2 text-[11px] font-medium transition',
                            comment.hasReacted
                                ? 'bg-brand-soft text-brand-ink'
                                : 'border-border text-muted-foreground hover:bg-secondary border',
                            !canContribute && 'cursor-default',
                        )}
                    >
                        <ThumbsUp className="size-3" aria-hidden />
                        {comment.reactions}
                    </button>
                    {canContribute && (
                        <button
                            type="button"
                            onClick={onReply}
                            className="border-border text-muted-foreground hover:bg-secondary hover:text-foreground inline-flex h-5.5 items-center gap-1 rounded-full border px-2 text-[11px] font-medium"
                        >
                            <MessageSquare className="size-3" aria-hidden />
                            Reply
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function CommentForm({
    taskId,
    me,
    parentId,
    placeholder = 'Leave a comment…',
    onDone,
    onCancel,
}: {
    taskId: number;
    me: Person;
    parentId?: number;
    placeholder?: string;
    onDone?: () => void;
    onCancel?: () => void;
}) {
    const form = useForm({ body: '', parent_id: parentId ?? null });

    function submit() {
        if (form.data.body.trim() === '') return;

        form.post(storeComment({ task: taskId }).url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
                onDone?.();
                toast.success('Comment posted.');
            },
        });
    }

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            className="flex gap-3"
        >
            <UserAvatar person={me} size="sm" />

            <div className="min-w-0 flex-1">
                <div className="border-input bg-card focus-within:border-brand-400 rounded-xl border shadow-xs">
                    <textarea
                        value={form.data.body}
                        onChange={(event) =>
                            form.setData('body', event.target.value)
                        }
                        onKeyDown={(event) => {
                            // ⌘/Ctrl + Enter posts, matching the prototype
                            if (
                                (event.metaKey || event.ctrlKey) &&
                                event.key === 'Enter'
                            ) {
                                event.preventDefault();
                                submit();
                            }
                        }}
                        rows={3}
                        placeholder={placeholder}
                        aria-label="Comment"
                        className="text-foreground placeholder:text-muted-foreground w-full resize-y bg-transparent px-3 py-2.5 text-[13px] outline-none"
                    />
                    <div className="border-border flex items-center justify-between border-t px-2 py-1.5">
                        <p className="text-muted-foreground px-1 text-[11px]">
                            <kbd className="border-border bg-secondary rounded border px-1 font-sans">
                                ⌘
                            </kbd>
                            <kbd className="border-border bg-secondary ml-0.5 rounded border px-1 font-sans">
                                ↵
                            </kbd>{' '}
                            to post
                        </p>
                        <div className="flex items-center gap-1.5">
                            {onCancel && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={onCancel}
                                >
                                    Cancel
                                </Button>
                            )}
                            <Button
                                type="submit"
                                size="sm"
                                disabled={
                                    form.processing ||
                                    form.data.body.trim() === ''
                                }
                            >
                                <Send className="size-4" aria-hidden />
                                Comment
                            </Button>
                        </div>
                    </div>
                </div>
                {form.errors.body && (
                    <p className="text-danger-ink mt-1.5 text-xs">
                        {form.errors.body}
                    </p>
                )}
            </div>
        </form>
    );
}
