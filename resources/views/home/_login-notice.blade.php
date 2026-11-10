{{-- Shown once, at the top of every role's home page, when the login form was sent from a browser that is already
     logged in (LoginController::alreadyLoggedIn): the first account stays, and this says why. --}}
@if (session('login_notice'))
  <x-ui.alert tone="warning" role="alert" class="rb-dashboard__notice" id="login-notice">{{ session('login_notice') }}</x-ui.alert>
@endif
