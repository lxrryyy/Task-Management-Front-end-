@props([
    'name' => 'content',
    'value' => '',
    'placeholder' => 'Start typing here…',
])

<div wire:ignore x-data="richEditor('{{ $name }}', @js($value))" x-init="init()"
    x-on:clear-rich-editor.window="if (($event.detail?.field || '') === fieldName) { clearEditor() }"
    class="w-full rounded-xl border border-base-300 bg-base-100 shadow-sm overflow-hidden">
    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-1 px-3 py-2 border-b border-base-300 bg-base-50">

        {{-- Text style --}}
        <div class="flex items-center gap-0.5">
            <button type="button" @mousedown.prevent="cmd('bold')"
                :class="states.bold ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-sm font-bold transition-colors">B</button>
            <button type="button" @mousedown.prevent="cmd('italic')"
                :class="states.italic ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-sm italic transition-colors">I</button>
            <button type="button" @mousedown.prevent="cmd('underline')"
                :class="states.underline ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-sm underline transition-colors">U</button>
            <button type="button" @mousedown.prevent="cmd('strikethrough')"
                :class="states.strike ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-sm line-through transition-colors">S</button>
        </div>

        <div class="w-px h-5 bg-base-300 mx-1"></div>

        {{-- Heading --}}
        <select @change="formatBlock($event.target.value)" x-model="states.block"
            class="h-8 px-2 text-xs rounded-lg border border-base-300 bg-base-100 text-base-content/70 focus:outline-none focus:border-base-400 cursor-pointer">
            <option value="p">Paragraph</option>
            <option value="h1">Heading 1</option>
            <option value="h2">Heading 2</option>
            <option value="h3">Heading 3</option>
        </select>

        <div class="w-px h-5 bg-base-300 mx-1"></div>

        {{-- Lists & blocks --}}
        <div class="flex items-center gap-0.5">
            <button type="button" @mousedown.prevent="cmd('insertUnorderedList')"
                :class="states.ul ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" title="Bullet list">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="9" y1="6" x2="20" y2="6" />
                    <line x1="9" y1="12" x2="20" y2="12" />
                    <line x1="9" y1="18" x2="20" y2="18" />
                    <circle cx="4" cy="6" r="1.5" fill="currentColor" stroke="none" />
                    <circle cx="4" cy="12" r="1.5" fill="currentColor" stroke="none" />
                    <circle cx="4" cy="18" r="1.5" fill="currentColor" stroke="none" />
                </svg>
            </button>
            <button type="button" @mousedown.prevent="cmd('insertOrderedList')"
                :class="states.ol ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" title="Numbered list">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="10" y1="6" x2="21" y2="6" />
                    <line x1="10" y1="12" x2="21" y2="12" />
                    <line x1="10" y1="18" x2="21" y2="18" /><text x="2" y="8" font-size="7"
                        fill="currentColor" stroke="none" font-weight="600">1</text><text x="2" y="14" font-size="7"
                        fill="currentColor" stroke="none" font-weight="600">2</text><text x="2" y="20" font-size="7"
                        fill="currentColor" stroke="none" font-weight="600">3</text>
                </svg>
            </button>
            <button type="button" @mousedown.prevent="formatBlock('blockquote')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
                title="Blockquote">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1zm12 0c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z" />
                </svg>
            </button>
            <button type="button" @mousedown.prevent="formatBlock('pre')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors font-mono text-xs"
                title="Code block">&lt;/&gt;</button>
        </div>

        <div class="w-px h-5 bg-base-300 mx-1"></div>

        {{-- Alignment --}}
        <div class="flex items-center gap-0.5">
            <button type="button" @mousedown.prevent="cmd('justifyLeft')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
                title="Align left">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="3" y1="12" x2="15" y2="12" />
                    <line x1="3" y1="18" x2="18" y2="18" />
                </svg>
            </button>
            <button type="button" @mousedown.prevent="cmd('justifyCenter')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
                title="Align center">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="6" y1="12" x2="18" y2="12" />
                    <line x1="4" y1="18" x2="20" y2="18" />
                </svg>
            </button>
            <button type="button" @mousedown.prevent="cmd('justifyRight')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
                title="Align right">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="3" y1="6" x2="21" y2="6" />
                    <line x1="9" y1="12" x2="21" y2="12" />
                    <line x1="6" y1="18" x2="21" y2="18" />
                </svg>
            </button>
        </div>

        <div class="w-px h-5 bg-base-300 mx-1"></div>

        {{-- Link & clear format --}}
        <div class="flex items-center gap-0.5">
            <button type="button" @mousedown.prevent="toggleLinkBar()"
                :class="showLink ? 'bg-base-200 text-base-content' :
                    'text-base-content/60 hover:bg-base-200 hover:text-base-content'"
                class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors" title="Insert link">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
                    <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
                </svg>
            </button>
            <button type="button" @mousedown.prevent="cmd('removeFormat')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors text-xs font-medium"
                title="Clear formatting">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 6l12 12M6 12h6m2.5-6H19a1 1 0 010 2h-1.5M4 20h7" />
                    <line x1="4" y1="4" x2="20" y2="20" stroke-width="1.5" />
                </svg>
            </button>
        </div>

        <div class="w-px h-5 bg-base-300 mx-1"></div>

        {{-- Undo / Redo --}}
        <div class="flex items-center gap-0.5">
            <button type="button" @mousedown.prevent="cmd('undo')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
                title="Undo">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M3 10h10a5 5 0 010 10H8" />
                    <polyline points="3 10 7 6 3 2 3 10" />
                </svg>
            </button>
            <button type="button" @mousedown.prevent="cmd('redo')"
                class="w-8 h-8 rounded-lg flex items-center justify-center text-base-content/60 hover:bg-base-200 hover:text-base-content transition-colors"
                title="Redo">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 10H11a5 5 0 000 10h5" />
                    <polyline points="21 10 17 6 21 2 21 10" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Link bar (hidden by default) --}}
    <div x-show="showLink" x-cloak x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        class="flex items-center gap-2 px-3 py-2 border-b border-base-300 bg-base-50">
        <svg class="w-4 h-4 text-base-content/40 shrink-0" fill="none" stroke="currentColor" stroke-width="2"
            viewBox="0 0 24 24">
            <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71" />
            <path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71" />
        </svg>
        <input x-model="linkUrl" type="url" placeholder="https://..."
            class="flex-1 h-8 px-3 text-sm rounded-lg border border-base-300 bg-base-100 focus:outline-none focus:border-base-400"
            @keydown.enter.prevent="applyLink()" @keydown.escape="showLink = false" />
        <button type="button" @click="applyLink()"
            class="h-8 px-3 text-xs rounded-lg bg-base-content text-base-100 hover:opacity-80 transition-opacity">Apply</button>
        <button type="button" @click="showLink = false"
            class="h-8 px-3 text-xs rounded-lg border border-base-300 hover:bg-base-200 transition-colors">Cancel</button>
    </div>

    {{-- Editable area --}}
    <div x-ref="editorEl" contenteditable="true" @input="onInput()" @keyup="syncState()" @mouseup="syncState()"
        :data-placeholder="placeholder"
        class="min-h-[160px] p-4 text-sm leading-relaxed outline-none prose max-w-none
               empty:before:content-[attr(data-placeholder)] empty:before:text-base-content/30
               [&_blockquote]:border-l-4 [&_blockquote]:border-base-300 [&_blockquote]:pl-3 [&_blockquote]:italic [&_blockquote]:text-base-content/70
               [&_pre]:bg-base-200 [&_pre]:rounded-lg [&_pre]:p-3 [&_pre]:text-xs [&_pre]:font-mono
               [&_a]:text-primary [&_a]:underline
               [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:text-lg [&_h3]:font-semibold">
    </div>

    {{-- Footer --}}
    <div class="flex items-center justify-between px-4 py-2 border-t border-base-300 bg-base-50">
        <span class="text-xs text-base-content/30" x-text="wordCount + ' words'"></span>
        <button type="button" @click="clearEditor()"
            class="text-xs text-base-content/40 hover:text-base-content/70 transition-colors">Clear</button>
    </div>

    {{-- Hidden input for form submission --}}
    <textarea :name="fieldName" x-model="htmlValue" class="hidden"></textarea>
</div>
