<nav class="bg-gray-800 text-white">
    <div class="container mx-auto flex items-center justify-between py-4">
        <div class="text-lg font-semibold">
            <a href="{{ route('index') }}" class="text-white hover:text-gray-300">Homepage</a>
        </div>
        <div class="space-x-4">
            <a href="{{ route('jobs.index') }}" class="hover:text-gray-300">Jobs</a>
            <a href="{{ route('companies.index') }}" class="hover:text-gray-300">Companies</a>
            <a href="{{ route('departments.index') }}" class="hover:text-gray-300">Departments</a>
            <a href="{{ route('signup') }}" class="hover:text-gray-300">Signup</a>
        </div>
    </div>