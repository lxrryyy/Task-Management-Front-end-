<div x-data="{ open: false }">
    <div class="flex w-full items-center clr-primary">
        <a href="/dashboard" class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('projects') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
        </a>
        <span class="group-hover:block text-xl">Audit Logs</span>
    </div>
    <hr class="border-2 border-gray-300" />

    <div class="flex flex-row justify-between mt-4">
        <div class="flex flex-row gap-4">
            <button @click="open = !open" class="btn border-2 border-gray clr-primary text-base-100 p-4 hover-clr-bg-primary hover:text-base-100">
                <x-icons.sort class="w-4 h-4 inline-block" /> Filter
            </button>
            <label class="input focus-within:outline-none bg-transparent focus-within:border-base-300 flex-1">
                <input wire:model.live.debounce.300ms="search" class="w-40 bg-transparent focus:outline-none rounded-lg" type="search" placeholder="Search" />
            </label>
        </div>
        <div class="flex flex-row gap-4">
            <button class="btn clr-bg-primary text-base-100 rounded-lg p-4"><x-icons.export classes="w-6 h-6" /> Export</button>
        </div>
    </div>

    <div x-show="open" x-transition class="flex flex-row gap-4 border border-gray-300 rounded-lg p-4 mt-2 bg-white">
        <div class="flex flex-col flex-1">
            <span>User</span>
            <select wire:model.live="filterUserId" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All User</option>
                @foreach($this->accounts as $acc)
                    @php
                        $aid = (int)($acc['id'] ?? 0);
                        $an  = (string)($acc['name'] ?? '');
                    @endphp
                    @if($aid > 0 && $an !== '')
                        <option value="{{ $aid }}">{{ $an }}</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Role</span>
            <select wire:model.live="filterRole" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All roles</option>
                @foreach($roleOptions ?? [] as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Project Name</span>
            <select wire:model.live="filterProject" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All projects</option>
                @foreach($projectOptions ?? [] as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Action</span>
            <select wire:model.live="filterAction" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All actions</option>
                @foreach($actionOptions ?? [] as $a)
                    <option value="{{ $a }}">{{ $a }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Status</span>
            <select wire:model.live="filterStatus" class="select select-bordered w-full bg-white text-gray-900">
                <option value="">All statuses</option>
                @foreach($statusOptions ?? [] as $s)
                    <option value="{{ $s }}">{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex flex-col flex-1">
            <span>Date From</span>
            <input wire:model.live="filterFrom" type="date" class="input input-sm input-bordered w-full bg-white text-gray-900" />
        </div>
        <div class="flex flex-col flex-1">
            <span>Date To</span>
            <input wire:model.live="filterTo" type="date" class="input input-sm input-bordered w-full bg-white text-gray-900" />
        </div>
    </div>

    @if($loadError)
        <div class="mt-3 alert alert-error text-sm flex items-center gap-2 py-2 px-4 rounded-lg">
            <span>{{ $loadError }}</span>
        </div>
    @endif

    <div class="overflow-x-auto mt-4 border border-gray-200 rounded-lg bg-white">
        <div wire:loading class="p-4 text-sm text-gray-500">Loading audit logs…</div>

        <table class="table w-full" wire:loading.remove>
            <thead class="bg-gray-50">
            <tr class="text-gray-500 text-xs uppercase tracking-wide">
                <th class="!font-normal">
                    <div class="flex items-center gap-1">
                        <span>User</span>
                        <span class="text-gray-400">↓</span>
                    </div>
                </th>
                <th class="!font-normal">Role</th>
                <th class="!font-normal">Project Name</th>
                <th class="!font-normal">Action</th>
                <th class="!font-normal">Description</th>
                <th class="!font-normal whitespace-nowrap">Date &amp; Time</th>
            </tr>
            </thead>
            <tbody>
            @forelse($filteredLogs ?? [] as $l)
                @php
                    $uid = (int)($l['accountId'] ?? 0);
                    $acc = ($uid > 0 && isset($accountMap[$uid]) && is_array($accountMap[$uid])) ? $accountMap[$uid] : ['name'=>'','email'=>'','role'=>''];

                    $uname = trim((string)($l['userName'] ?? ''));
                    if ($uname === '' && $uid > 0) $uname = trim((string)($acc['name'] ?? ''));
                    if ($uname === '') $uname = '—';

                    $uemail = trim((string)($l['userEmail'] ?? ''));
                    if ($uemail === '' && $uid > 0) $uemail = trim((string)($acc['email'] ?? ''));

                    $urole = trim((string)($l['userRole'] ?? ''));
                    if ($urole === '' && $uid > 0) $urole = trim((string)($acc['role'] ?? ''));
                    if ($urole === '') $urole = '—';

                    $project = trim((string)($l['projectName'] ?? '')) ?: '—';

                    $actionRaw = trim((string)($l['action'] ?? ''));
                    $action = $actionRaw !== '' ? mb_strtoupper($actionRaw) : '—';
                    $actionStyle = match($action) {
                        'DELETED' => 'bg-red-100 text-red-700',
                        'UPDATED' => 'bg-pink-100 text-pink-700',
                        'CREATED' => 'bg-blue-100 text-blue-700',
                        'OPENED'  => 'bg-gray-100 text-gray-700',
                        default   => 'bg-gray-100 text-gray-700',
                    };

                    $desc = trim((string)($l['message'] ?? ''));
                    $entity = trim((string)($l['entity'] ?? ''));
                    if ($desc === '' && $entity !== '') $desc = $entity;
                    if ($desc === '') $desc = '—';

                    $atRaw = (string)($l['at'] ?? '');
                    $dateLabel = '—';
                    $timeLabel = '';
                    if ($atRaw !== '') {
                        try {
                            $c = \Carbon\Carbon::parse($atRaw);
                            $dateLabel = $c->format('F d, Y');
                            $timeLabel = $c->format('h:ia');
                        } catch (\Throwable) {
                            $dateLabel = $atRaw;
                        }
                    }
                @endphp

                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="py-4">
                        <div class="flex flex-col leading-tight">
                            <span class="font-medium text-gray-900">{{ $uname }}</span>
                            @if($uemail !== '')
                                <span class="text-xs text-gray-500">{{ $uemail }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="py-4 text-gray-700 whitespace-nowrap">{{ $urole }}</td>
                    <td class="py-4 text-gray-700">{{ $project }}</td>
                    <td class="py-4 whitespace-nowrap">
                        @if($action !== '—')
                            <span class="px-2 py-1 rounded text-[10px] font-semibold {{ $actionStyle }}">{{ $action }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="py-4 text-gray-700 max-w-[520px] whitespace-pre-wrap break-words">{{ $desc }}</td>
                    <td class="py-4 text-gray-700 whitespace-nowrap">
                        <div class="flex flex-col leading-tight">
                            <span>{{ $dateLabel }}</span>
                            @if($timeLabel !== '')
                                <span class="text-xs text-gray-500">{{ $timeLabel }}</span>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-10 text-gray-500">No audit logs found.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
