@props([
    'taskId'        => null,
    'initialStatus' => '',
])

@php
    $isCompleted = mb_strtolower(trim($initialStatus)) === 'completed';
    // When unchecking, revert to the previous status.
    // If the task is already Completed on load, fall back to 'Not Started'.
    $revertStatus = $isCompleted ? 'Not Started' : $initialStatus;
@endphp

{{--
    Task-row checkbox. Visually matches the person-option checkbox box.
    Checking  → dispatches task-status-changed with newStatus = 'Completed'
    Unchecking → dispatches task-status-changed with newStatus = previous status
--}}
<span x-data="{
          on:         {{ $isCompleted ? 'true' : 'false' }},
          prevStatus: '{{ addslashes($revertStatus) }}',
          toggle() {
              this.on = !this.on;
              const newStatus = this.on ? 'Completed' : this.prevStatus;
              @if($taskId)
              Livewire.dispatch('task-status-changed', {
                  taskId:    {{ (int) $taskId }},
                  newStatus: newStatus
              });
              @endif
          }
      }"
      @click.stop="toggle()"
      {{ $attributes->merge(['class' => 'inline-flex items-center justify-center h-4 w-4 rounded border border-gray-400 bg-white cursor-pointer flex-shrink-0 select-none']) }}>
    <template x-if="on">
        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect x="0" y="0" width="20" height="20" rx="4" fill="#111827"/>
            <path d="M5 10.5L8.25 13.75L15 7" stroke="#FFFFFF" stroke-width="2"
                  stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </template>
</span>
