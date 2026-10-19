@include('components/backend/header')

@yield('content')

{{ $slot ?? '' }}

@include('components/backend/footer')