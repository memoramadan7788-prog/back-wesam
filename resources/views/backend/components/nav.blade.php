<nav class="bg-gray-800 text-white">
    <div class="container mx-auto flex items-center justify-between py-4">
        <div class="text-lg font-semibold">
            <a href="{{ route('dashboard') }}" class="text-white hover:text-gray-300">HR Management System</a>
        </div>
        <div class="space-x-4">
            <a href="{{ route('dashboard') }}" class="hover:text-gray-300">Dashboard</a>
            <a href="{{ route('employees.index') }}" class="hover:text-gray-300">Employees</a>
            <a href="{{ route('departments.index') }}" class="hover:text-gray-300">Departments</a>
            <a href="{{ route('logout') }}" class="hover:text-gray-300">Logout</a>
        </div>
    </div>