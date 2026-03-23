<div>
    <div class="w-full">
        <div class="flex w-full items-center clr-primary ">
            <a href="/dashboard"
            class="flex items-center gap-4 px-3 py-3 rounded-lg whitespace-nowrap {{ request()->is('settings') ? 'clr-primary' : '' }} hover-clr-accent">
            <x-icons.back-btn classes="w-6 h-6" />
            </a>
            <span class="group-hover:block text-xl">Account Settings</span>
        </div>
        <hr class="border-2 clr-bg-primary">

        <div class="flex flex-col p-4">
            <div class="flex flex-row gap-2">
                <div class="h-24 w-24 border-2 border-red-500 rounded-full"></div>
                <div class="flex flex-col">
                    <h1 class="font-semibold text-xl">Airone Gamil</h1>
                    <span>airone.gamil@gmail.com</span>
                </div>
            </div>
            <button class="btn w-48 text-base-100 mt-4 clr-bg-primary rounded-lg p-4">Upload Photo</button>
            <div class="flex flex-row justify-between mt-4 gap-8">
                <div class="flex flex-col flex-1">
                    <span>First Name</span>
                    <input class="w-4/5" type="text">
                </div>
                <div class="flex flex-col flex-1">
                    <span>Last Name</span>
                    <input class="w-4/5" type="text">
                </div>
            </div>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>Bio/Specialization</span>
                    <input type="text">
                    <span>Shows under your name</span>
                </div>
                <div class="flex flex-col flex-1">

                </div>
            </div>
            <hr class="border-2 mt-4">
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>Current Password</span>
                    <input type="password">
                </div>
                <div class="flex flex-col flex-1">

                </div>
            </div>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>New Password</span>
                    <input type="password">
                </div>
                <div class="flex flex-col flex-1">

                </div>
            </div>
            <div class="flex justify-between gap-8">
                <div class="flex flex-col gap-2 flex-1 mt-2">
                    <span>Confirm Password</span>
                    <input type="password">
                </div>
                <div class="flex flex-col flex-1">

                </div>
            </div>
        </div>
        <div class="flex justify-end">
            <button class="btn w-48 text-base-100 mt-4 clr-bg-primary rounded-lg p-4">Save Changes</button>
        </div>
    </div>
</div>
