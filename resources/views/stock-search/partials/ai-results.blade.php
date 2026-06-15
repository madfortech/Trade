<!-- RIGHT AI -->
<div id="aiPanel"
    class="flex flex-col h-full bg-white overflow-hidden border-l">

    <!-- ANALYZE BUTTON -->
    <div class="p-3 border-b">

        <button
            id="aiAnalyzeBtn"
            onclick="runAIAnalysis()"
            class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-xs font-bold text-white">

            ⚡ Analyze

        </button>

    </div>

    <!-- SCROLLABLE RESULT -->
    <div class="min-h-0 flex-1 overflow-y-auto px-3 py-3">

        <!-- AI WAITING -->

        <div id="aiWaiting"
            class="flex items-center justify-center p-4 text-center">

            <p class="text-xs leading-relaxed text-slate-500">

                <strong class="text-indigo-600">
                    Analyze
                </strong>

                to generate AI insights.

            </p>

        </div>

        <!-- AI SKELETON -->
        <div id="aiSkeleton"
            class="hidden p-3 space-y-3">

            <div
                class="h-[72px] animate-pulse rounded-2xl bg-slate-100">
            </div>

            <div class="grid grid-cols-2 gap-2">

                <div
                    class="h-[70px] animate-pulse rounded-xl bg-slate-100">
                </div>

                <div
                    class="h-[70px] animate-pulse rounded-xl bg-slate-100">
                </div>

                <div
                    class="h-[70px] animate-pulse rounded-xl bg-slate-100">
                </div>

                <div
                    class="h-[70px] animate-pulse rounded-xl bg-slate-100">
                </div>

            </div>

        </div>

        <!-- AI VERDICT AREA -->
        <div id="aiVerdictArea"
            class="space-y-3 bg-white p-3"
            style="display:none;">

            <!-- TOP CARD -->
            <div id="ai-verdict-box"
                class="flex items-start gap-3 rounded-2xl border border-indigo-100 bg-gradient-to-br from-indigo-50 to-white p-4 shadow-md">

                <div id="ai-icon"
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-2xl text-white shadow-md">

                    📊

                </div>

                <div class="min-w-0 flex-1">

                    <div id="ai-title"
                        class="text-[14px] font-black leading-tight tracking-wide text-slate-800">
                        --
                    </div>

                    <div id="ai-confidence"
                        class="mt-1 text-[11px] font-semibold text-slate-500">
                        --
                    </div>

                </div>

            </div>

            <!-- SIGNALS -->
            <div class="grid grid-cols-2 gap-2">

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm">

                    <div
                        class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Trend
                    </div>

                    <div id="ai-trend"
                        class="text-[13px] font-black text-slate-800">
                        --
                    </div>

                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm">

                    <div
                        class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Momentum
                    </div>

                    <div id="ai-momentum"
                        class="text-[13px] font-black text-slate-800">
                        --
                    </div>

                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm">

                    <div
                        class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Volume
                    </div>

                    <div id="ai-vol-sig"
                        class="text-[13px] font-black text-slate-800">
                        --
                    </div>

                </div>

                <div
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 shadow-sm">

                    <div
                        class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        Risk
                    </div>

                    <div id="ai-risk"
                        class="text-[13px] font-black text-slate-800">
                        --
                    </div>

                </div>

            </div>

            <!-- LEVELS -->
            <div class="grid grid-cols-2 gap-2">

                <div
                    class="rounded-xl border border-green-200 bg-green-50 p-3 shadow-sm">

                    <div
                        class="mb-1 text-[10px] font-bold uppercase tracking-wider text-green-600">
                        Support
                    </div>

                    <div id="ai-support"
                        class="text-[14px] font-black text-green-700">
                        --
                    </div>

                </div>

                <div
                    class="rounded-xl border border-red-200 bg-red-50 p-3 shadow-sm">

                    <div
                        class="mb-1 text-[10px] font-bold uppercase tracking-wider text-red-600">
                        Resistance
                    </div>

                    <div id="ai-resist"
                        class="text-[14px] font-black text-red-700">
                        --
                    </div>

                </div>

            </div>

            <!-- REASONS -->
            <div id="aiReasons"
                class="space-y-2 rounded-xl border border-slate-200 bg-slate-50 p-3 text-[12px]">
            </div>

            <!-- FOOTER -->
            <div id="ai-updated"
                class="pb-4 text-center text-[10px] text-slate-400">
                --
            </div>

        </div>

    </div>

</div>