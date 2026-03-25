<x-app-layout>
    <x-slot name="header">
        <h2 class="font-normal text-xl text-gray-800 leading-tight">
            {{ __('Tasks') }}
        </h2>
    </x-slot>

    <div class="w-full mx-auto sm:px-6 lg:px-8">
        @livewire('tasks', [
            'projectId'        => $projectId ?? null,
            'tasks'            => $tasks ?? [],
            'accounts'         => $accounts ?? [],
            'showAddTaskModal' => ($errors->any() && old('name') !== null) || (session('task_warnings') && count(session('task_warnings')) > 0),
            'taskParentId'     => old('parentTaskId') ? (int) old('parentTaskId') : null,
            'openTaskId'       => request()->filled('openTask') ? (int) request('openTask') : (request()->filled('taskId') ? (int) request('taskId') : null),
            'openCommentId'    => request()->filled('comment') ? (int) request('comment') : null,
        ])
    </div>
</x-app-layout>
@if(request()->filled('comment') && (request()->filled('openTask') || request()->filled('taskId')))
    @php $deepLinkCommentId = (int) request('comment'); @endphp
    @if($deepLinkCommentId > 0)
        <script>
            (function () {
                var commentId = {{ $deepLinkCommentId }};
                function focusComment() {
                    var el = document.getElementById('task-cmt-' + commentId);
                    if (!el) return;
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    el.classList.add('ring-2', 'ring-blue-400', 'rounded-lg');
                    window.setTimeout(function () { el.classList.remove('ring-2', 'ring-blue-400'); }, 2200);
                }
                document.addEventListener('DOMContentLoaded', function () {
                    window.setTimeout(focusComment, 200);
                    window.setTimeout(focusComment, 700);
                });
            })();
        </script>
    @endif
@endif
