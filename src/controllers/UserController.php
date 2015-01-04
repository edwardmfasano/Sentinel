<?php namespace Sentinel;

use Sentinel\Repositories\Group\SentinelGroupRepositoryInterface;
use Sentinel\Repositories\User\SentinelUserRepositoryInterface;
use Sentinel\Services\Forms\ChangePasswordForm;
use Sentinel\Services\Forms\ForgotPasswordForm;
use Sentinel\Services\Forms\RegisterForm;
use Sentinel\Services\Forms\ResendActivationForm;
use Sentinel\Services\Forms\UserCreateForm;
use BaseController, View, Input, Event, Redirect, Session, Config, Paginator;
use Sentinel\Services\Forms\UserUpdateForm;
use Sentinel\Traits\SentinelRedirectionTrait;

class UserController extends BaseController {

    /**
     * Members
     */
    protected $user;
    protected $group;
    protected $userCreateForm;
    protected $userUpdateForm;
    protected $changePasswordForm;

    /**
     * Traits
     */
    use SentinelRedirectionTrait;

    /**
     * Constructor
     */
    public function __construct(
        SentinelUserRepositoryInterface $userRepository,
        SentinelGroupRepositoryInterface $groupRepository,
        UserCreateForm $userCreateForm,
        UserUpdateForm $userUpdateForm,
        ChangePasswordForm $changePasswordForm
    ) {
        $this->userRepository     = $userRepository;
        $this->groupRepository    = $groupRepository;
        $this->userCreateForm     = $userCreateForm;
        $this->userUpdateForm     = $userUpdateForm;
        $this->changePasswordForm = $changePasswordForm;

        //Check CSRF token on POST
        $this->beforeFilter('Sentinel\csrf', ['on' => ['post', 'put', 'delete']]);

        // Set up Auth Filters
        $this->beforeFilter('Sentinel\auth', ['only' => ['show', 'edit', 'update', 'change']]);
        $this->beforeFilter(
            'Sentinel\hasAccess:admin',
            ['only' => ['index', 'create', 'add', 'destroy', 'suspend', 'unsuspend', 'ban', 'unban']]
        );
    }

    /**
     * Display a paginated index of all current users, with throttle data
     *
     * @return View
     */
    public function index()
    {
        // Paginate the existing users
        $users       = $this->userRepository->all();
        $perPage     = 15;
        $currentPage = Input::get('page') - 1;
        $pagedData   = array_slice($users, $currentPage * $perPage, $perPage);
        $users       = Paginator::make($pagedData, count($users), $perPage);

        return View::make('Sentinel::users.index')->with('users', $users);
    }


    /**
     * Show the "Create new User" form
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return View::make('Sentinel::users.create');
    }

    /**
     * Create a new user account manually
     *
     * @return Redirect
     */
    public function store()
    {
        // Collect Data
        $data = Input::all();

        // Validate form data
        $this->userCreateForm->validate($data);

        // Create and store the new user
        $result = $this->userRepository->store($data);

        // Determine response message based on whether or not the user was activated
        $message = ($result->getPayload()['activated'] ? trans('Sentinel::users.addedactive') : trans('Sentinel::users.added'));

        // Finished!
        return $this->redirectTo('users.store', ['success' => $message]);
    }


    /**
     * Show the profile of a specific user account
     *
     * @param $id
     *
     * @return Redirect|View
     */
    public function show($id)
    {
        // This action can only be executed if the operator is an admin,
        // or is this specific user
        $isOwner = $this->profileOwner($id);
        if ($isOwner !== true) {
            return $isOwner;
        }

        // Get the user
        $user = $this->userRepository->retrieveById($id);

        return View::make('Sentinel::users.show')->with('user', $user);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     *
     * @return Redirect
     */
    public function edit($id)
    {
        // This action can only be executed if the operator is an admin,
        // or is this specific user
        $isOwner = $this->profileOwner($id);
        if ($isOwner !== true) {
            return $isOwner;
        }

        // Get the user
        $user = $this->userRepository->retrieveById($id);

        // Get all available groups
        $groups = $this->groupRepository->all();

        return View::make('Sentinel::users.edit')
            ->with('user', $user)
            ->with('groups', $groups);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int $id
     *
     * @return Redirect
     */
    public function update($id)
    {
        // This action can only be executed if the operator is an admin,
        // or is this specific user
        $isOwner = $this->profileOwner($id);
        if ($isOwner !== true) {
            return $isOwner;
        }

        // Gather Input
        $data = Input::all();

        // Validate form data
        $this->userUpdateForm->validate($data);

        // Attempt to update the user
        $result = $this->userRepository->update($data);

        // Done!
        return $this->redirectViaResponse('users.update', $result);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     *
     * @return Redirect
     */
    public function destroy($id)
    {
        $result = $this->userRepository->destroy($id);

        return $this->redirectViaResponse('users.destroy', $result);
    }

    /**
     * Change the group memberships for a given user
     *
     * @param $id
     *
     * @return \Response
     */
    public function updateGroupMemberships($id)
    {
        // Gather input
        $groups = Input::get('groups');

        // Change memberships
        $result = $this->userRepository->changeGroupMemberships($id, $groups);

        // Done
        return $this->redirectViaResponse('users.change.memberships', $result);
    }

    /**
     * Process a password change request
     *
     * @param  int $id
     *
     * @return redirect
     */
    public function changePassword($id)
    {
        // Gather input
        $data       = Input::all();
        $data['id'] = $id;

        // Validate form Data
        $this->changePasswordForm->validate($data);

        // Grab the current user
        $user = $this->userRepository->getUser();

        // Change the User's password
        $result = ($user->hasAccess('admin') ? $this->userRepository->changePasswordWithoutCheck($data) : $this->userRepository->changePassword($data));

        // Was the change successful?
        if (! $result->isSuccessful())
        {
            Session::flash('error', $result->getMessage());
            return Redirect::back();
        }
        return $this->redirectViaResponse('users.change.password', $result);
    }

    /**
     * Process a suspend user request
     *
     * @param  int $id
     *
     * @return Redirect
     */
    public function suspend($id)
    {
        $result = $this->userRepository->suspend($id);

        return $this->redirectViaResponse('users.suspend', $result);
    }

    /**
     * Unsuspend user
     *
     * @param  int $id
     *
     * @return Redirect
     */
    public function unsuspend($id)
    {
        $result = $this->userRepository->unsuspend($id);

        return $this->redirectViaResponse('users.unsuspend', $result);
    }

    /**
     * Ban a user
     *
     * @param  int $id
     *
     * @return Redirect
     */
    public function ban($id)
    {
        $result = $this->userRepository->ban($id);

        return $this->redirectViaResponse('users.ban', $result);
    }

    /**
     * Unban a user
     *
     * @param $id
     *
     * @return Redirect
     */
    public function unban($id)
    {
        $result = $this->userRepository->unban($id);

        return $this->redirectViaResponse('users.unban', $result);
    }

    /**
     * Check if the current user can update a profile
     *
     * @param $id
     *
     * @return bool|\Illuminate\Http\RedirectResponse
     */
    protected function profileOwner($id)
    {
        $user = $this->userRepository->getUser();

        if ($id != Session::get('userId') && (! $user->hasAccess('admin'))) {
            return $this->redirectTo('users.invalid', ['error' => trans('Sentinel::users.noaccess')]);
        }

        return true;
    }

}


