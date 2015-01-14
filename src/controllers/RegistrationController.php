<?php namespace Sentinel;

use Hashids\Hashids;
use Illuminate\Http\Response;
use Sentinel\Repositories\Group\SentinelGroupRepositoryInterface;
use Sentinel\Repositories\User\SentinelUserRepositoryInterface;
use Sentinel\Services\Forms\ChangePasswordForm;
use Sentinel\Services\Forms\ForgotPasswordForm;
use Sentinel\Services\Forms\RegisterForm;
use Sentinel\Services\Forms\ResendActivationForm;
use Sentinel\Services\Forms\ResetPasswordForm;
use Sentinel\Services\Forms\UserCreateForm;
use BaseController, View, Input, Event, Redirect, Session, Config;
use Sentinel\Traits\SentinelRedirectionTrait;
use Sentinel\Traits\SentinelViewfinderTrait;

class RegistrationController extends BaseController
{
    /**
     * Members
     */
    protected $user;
    protected $group;
    protected $registerForm;
    protected $resendActivationForm;
    protected $forgotPasswordForm;
    protected $resetPasswordForm;

    /**
     * Traits
     */
    use SentinelRedirectionTrait;
    use SentinelViewfinderTrait;

    /**
     * Constructor
     */
    public function __construct(
        SentinelUserRepositoryInterface $userRepository,
        SentinelGroupRepositoryInterface $groupRepository,
        RegisterForm $registerForm,
        ResendActivationForm $resendActivationForm,
        ForgotPasswordForm $forgotPasswordForm,
        ResetPasswordForm $resetPasswordForm,
        Hashids $hashids
    ) {
        $this->userRepository       = $userRepository;
        $this->groupRepository      = $groupRepository;
        $this->registerForm         = $registerForm;
        $this->resendActivationForm = $resendActivationForm;
        $this->forgotPasswordForm   = $forgotPasswordForm;
        $this->resetPasswordForm    = $resetPasswordForm;
        $this->hashids              = $hashids;

        //Check CSRF token on POST
        $this->beforeFilter('Sentinel\csrf', array('on' => array('post', 'put', 'delete')));
    }

    /**
     * Show the registration form, if registration is allowed
     *
     * @return Response
     */
    public function registration()
    {
        // Is this user already signed in? If so redirect to the post login route
        if (\Sentry::check()) {
            return $this->redirectTo('session.store');
        }

        //If registration is currently disabled, show a message and redirect home.
        if ( ! Config::get('Sentinel::auth.registration', false)) {
            return $this->redirectTo(['route' => 'home'], ['error' => trans('Sentinel::users.inactive_reg')]);
        }

        // All clear - show the registration form.
        return $this->viewFinder('Sentinel::users.register');
    }

    /**
     * Process a registration request
     *
     * @return Response
     */
    public function register()
    {
        // Validate Form Data
        $data = Input::all();
        $this->registerForm->validate($data);

        // Attempt Registration
        $result = $this->userRepository->store($data);

        // It worked!  Use config to determine where we should go.
        return $this->redirectViaResponse('registration.complete', $result);
    }

    /**
     * Activate a new user
     *
     * @param  int    $id
     * @param  string $code
     *
     * @return Response
     */
    public function activate($hash, $code)
    {
        // Decode the hashid
        $id = $this->hashids->decode($hash)[0];

        // Attempt the activation
        $result = $this->userRepository->activate($id, $code);

        // It worked!  Use config to determine where we should go.
        return $this->redirectViaResponse('registration.activated', $result);
    }

    /**
     * Show the 'Resend Activation' form
     *
     * @return \Illuminate\View\View
     */
    function resendActivationForm()
    {
        return $this->viewFinder('Sentinel::users.resend');
    }

    /**
     * Process resend activation request
     * @return Response
     */
    public function resendActivation()
    {
        // Validate form data
        $this->resendActivationForm->validate(Input::only('email'));

        // Resend the activation email
        $result = $this->userRepository->resend(['email' => e(Input::get('email'))]);

        // It worked!  Use config to determine where we should go.
        return $this->redirectViaResponse('registration.resend', $result);
    }

    /**
     * Display the "Forgot Password" form
     *
     * @return \Illuminate\View\View
     */
    public function forgotPasswordForm()
    {
        return $this->viewFinder('Sentinel::users.forgot');
    }


    /**
     * Process Forgot Password request
     * @return Response
     */
    public function sendResetPasswordEmail()
    {
        // Validate form data
        $this->forgotPasswordForm->validate(Input::only('email'));

        // Send Password Reset Email
        $result = $this->userRepository->triggerPasswordReset(Input::get('email'));

        // It worked!  Use config to determine where we should go.
        return $this->redirectViaResponse('registration.reset.triggered', $result);

    }

    /**
     * A user is attempting to reset their password
     *
     * @param $id
     * @param $code
     *
     * @return Redirect|View
     */
    public function passwordResetForm($hash, $code)
    {
        // Decode the hashid
        $id = $this->hashids->decode($hash)[0];

        // Validate Reset Code
        $result = $this->userRepository->validateResetCode($id, $code);

        if (! $result->isSuccessful())
        {
            return $this->redirectViaResponse('registration.reset.invalid', $result);
        }

        return $this->viewFinder('Sentinel::users.reset', [
            'hash' => $hash,
            'code' => $code
        ]);
    }

    /**
     * Process a password reset form submission
     *
     * @param $id
     * @param $code
     */
    public function resetPassword($hash, $code)
    {
        // Decode the hashid
        $id = $this->hashids->decode($hash)[0];

        // Gather input data
        $data = Input::only('password', 'password_confirmation');

        // Validate Form Data
        $this->resetPasswordForm->validate($data);

        // Change the user's password
        $result = $this->userRepository->resetPassword($id, $code, $data['password']);

        // It worked!  Use config to determine where we should go.
        return $this->redirectViaResponse('registration.reset.complete', $result);
    }

}

