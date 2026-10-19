@include('components/frontend/header')

@yield('content')

{{ $slot ?? '' }}

@include('components/frontend/footer')