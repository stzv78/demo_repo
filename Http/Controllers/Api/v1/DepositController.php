<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DepositDraftRequest;
use App\Http\Traits\ApiResponseTrait;
use App\Http\Traits\PaginationTrait;
use App\Models\Deposit;
use App\Models\DepositDraft;
use App\Notifications\DepositCreated;
use App\Services\DepositService;
use App\Services\IRISApiService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class DepositController extends Controller
{
    use ApiResponseTrait, PaginationTrait, IpchainRegister;

    public function __construct()
    {
        $this->depositService = App::make(DepositService::class);
        $this->registerService = App::make(IRISApiService::class);

        $this->middleware(['exist:deposit', 'has.access'])->except(['index', 'store', 'plugin', 'decrypt']);
        $this->middleware(['customer.subscribed'])->only(['register']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $list = $this->depositService->setQuery($request->query)->getList($request->user()->id);

        if (!$list->count()) {
            $perPage = intval($request->query('perPage')) ?? 20;
            $data = (new SpaPaginator($list, $list->count(), $perPage, 1))
                ->setPath($request->path())
                ->setPageName('currentPage')
                ->appends(['perPage' => $perPage]);

            return $this->sendSuccessResponse($data, Response::HTTP_NO_CONTENT);
        }

        $data = $this->paginate($list, $request);

        if (!$data->items()) {
            $message = trans('messages.api.page_not_found');
            return $this->sendFailedResponse($message, Response::HTTP_NOT_FOUND);
        }

        return $this->sendSuccessResponse($data, Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $draftRequest = DepositDraftRequest::createFrom($request);
        $data = $this->depositService->setModel(new DepositDraft())->setRequest($draftRequest)->storeDeposit();

        return $this->sendSuccessResponse($data, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function details(Request $request, $id)
    {
        $deposit = $this->depositService->getDetails($id);

        return $this->sendSuccessResponse($deposit);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $deposit = DepositDraft::find($id);

        if (!$deposit->updateable()) {
            $message = trans('messages.api.deposit.not_updateable');
            return $this->sendSuccessResponse($message, Response::HTTP_FORBIDDEN);
        };

        $newRequest = (new class() extends DepositDraftRequest
        {
        })->createFrom($request);

        $this->depositService->setModel($deposit)->setRequest($newRequest);

        $data = $this->depositService->updateDeposit();

        if ($data instanceof \Illuminate\Validation\ValidationException) {
            return $this->sendFailedResponse($data->errors(), $data->status);
        }

        if ($data instanceof \Exception) {
            return $this->sendFailedResponse($data->getMessage(), $data->getCode());
        }

        return $this->sendSuccessResponse($data, Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        $deposit = Deposit::find($id);

        if (!$deposit->deleteable()) {
            $message = trans('messages.api.deposit.not_deleteable');
            return $this->sendSuccessResponse($message, Response::HTTP_FORBIDDEN);
        };

        $data = $this->depositService->setModel($deposit)->deleteDeposit();

        return $this->sendSuccessResponse($data, Response::HTTP_OK);
    }

    public function plugin(Request $request)
    {
        $draftRequest = DepositDraftRequest::createFrom($request);
        $deposit = $this->depositService
            ->setModel(new DepositDraft())
            ->setRequest($draftRequest)
            ->storePlugin();

        $this->notifyPluginUser($deposit->user, $deposit);

        return $this->sendSuccessResponse($deposit, Response::HTTP_CREATED);
    }

    protected function notifyPluginUser(User $user, Deposit $deposit)
    {
        $notification = new DepositCreated($deposit);

        try {
            $user->notify($notification);

            \Illuminate\Support\Facades\Log::log(
                'info',
                'Created', [
                'context' => [
                    'type' => 'notify',
                    'className' => class_basename($notification),
                    'body' => json_encode($notification),
                ],
            ]);
        } catch (\Exception $exception) {
            \Illuminate\Support\Facades\Log::alert(
                "Error notify deposit created: userId=$user->id, deposit=$deposit->id", [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);
        }
    }
}

