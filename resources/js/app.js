import "./bootstrap";
import Chart from "chart.js/auto";
import { registerNotifications } from "./notifications";

window.Chart = Chart;

window.countUpNumber = function countUpNumber(targetValue, durationMs) {
    const safeTarget = Math.max(0, parseInt(targetValue || 0, 10));
    const safeDuration = Math.max(250, parseInt(durationMs || 700, 10));
    return {
        target: safeTarget,
        duration: safeDuration,
        display: "0",
        start() {
            const started = performance.now();
            const animate = (now) => {
                const progress = Math.min((now - started) / this.duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                const current = Math.round(this.target * eased);
                this.display = current.toLocaleString();
                if (progress < 1) requestAnimationFrame(animate);
            };
            requestAnimationFrame(animate);
        },
    };
};

document.addEventListener("livewire:init", () => {
    registerNotifications();

    window.Alpine.data(
        "richEditor",
        (fieldName, initialValue = "", placeholder = "") => ({
            fieldName,
            htmlValue: initialValue,
            placeholder,
            showLink: false,
            linkUrl: "",
            wordCount: 0,
            states: {
                bold: false,
                italic: false,
                underline: false,
                strike: false,
                ul: false,
                ol: false,
                block: "p",
            },
            init() {
                const setup = () => {
                    this.$refs.editorEl.innerHTML =
                        this.htmlValue || "<p><br></p>";
                    this.updateWordCount();
                    this.$nextTick(() => {
                        this.$refs.editorEl.focus();
                        document.execCommand("selectAll", false, null);
                        const sel = window.getSelection();
                        if (sel && sel.rangeCount > 0) {
                            const range = sel.getRangeAt(0);
                            range.collapse(true);
                            this.savedRange = range;
                        }
                        this.$refs.editorEl.blur();
                    });
                };
                if (this.$refs.editorEl.offsetParent !== null) {
                    setup();
                    return;
                }
                const observer = new IntersectionObserver((entries) => {
                    if (entries[0].isIntersecting) {
                        setup();
                        observer.disconnect();
                    }
                });
                observer.observe(this.$refs.editorEl);
            },
            cmd(command) {
                document.execCommand(command, false, null);
                this.$refs.editorEl.focus();
                this.syncState();
                this.onInput();
            },
            formatBlock(tag) {
                document.execCommand("formatBlock", false, tag);
                this.$refs.editorEl.focus();
                this.syncState();
            },
            toggleLinkBar() {
                this.showLink = !this.showLink;
                if (this.showLink)
                    this.$nextTick(() =>
                        this.$el.querySelector('input[type="url"]')?.focus(),
                    );
            },
            applyLink() {
                if (this.linkUrl)
                    document.execCommand("createLink", false, this.linkUrl);
                this.showLink = false;
                this.linkUrl = "";
                this.$refs.editorEl.focus();
            },
            onInput() {
                this.htmlValue = this.$refs.editorEl.innerHTML;
                this.updateWordCount();
                this.syncState();

                const component = Livewire.find(
                    this.$el.closest("[wire\\:id]")?.getAttribute("wire:id"),
                );
                if (component) {
                    component.set(this.fieldName, this.htmlValue);
                }
            },
            syncState() {
                this.states.bold = document.queryCommandState("bold");
                this.states.italic = document.queryCommandState("italic");
                this.states.underline = document.queryCommandState("underline");
                this.states.strike =
                    document.queryCommandState("strikethrough");
                this.states.ul = document.queryCommandState(
                    "insertUnorderedList",
                );
                this.states.ol =
                    document.queryCommandState("insertOrderedList");
                const block = document
                    .queryCommandValue("formatBlock")
                    .toLowerCase();
                this.states.block = ["h1", "h2", "h3"].includes(block)
                    ? block
                    : "p";
            },
            updateWordCount() {
                const text = this.$refs.editorEl.innerText.trim();
                this.wordCount = text
                    ? text.split(/\s+/).filter(Boolean).length
                    : 0;
            },
            clearEditor() {
                this.$refs.editorEl.innerHTML = "<p><br></p>";
                this.htmlValue = "";
                this.wordCount = 0;

                const component = Livewire.find(
                    this.$el.closest("[wire\\:id]")?.getAttribute("wire:id"),
                );
                if (component) {
                    component.set(this.fieldName, "");
                }
            },
        }),
    );
});
