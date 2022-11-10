<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\API\BaseController;
use Throwable;
use Request;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {

        //Unhandled Routes – Fallback Method for GET
        if ((Request::isMethod('get') && $exception instanceof MethodNotAllowedHttpException) || (Request::isMethod('get') && $exception instanceof NotFoundHttpException)) {

            return BaseController::sendError('Not Found');
        }

        //Unhandled Routes – Fallback Method for POST
        if ((Request::isMethod('post') && $exception instanceof MethodNotAllowedHttpException) || (Request::isMethod('post') && $exception instanceof NotFoundHttpException)) {

            return BaseController::sendError('Not Found');
        }


        //Unhandled Routes – Fallback Method for PUT
        if ((Request::isMethod('put') && $exception instanceof MethodNotAllowedHttpException) || (Request::isMethod('put') && $exception instanceof NotFoundHttpException)) {

            return BaseController::sendError('Not Found');
        }

        //Unhandled Routes – Fallback Method for PATCH
        if ((Request::isMethod('patch') && $exception instanceof MethodNotAllowedHttpException) || (Request::isMethod('patch') && $exception instanceof NotFoundHttpException)) {

            return BaseController::sendError('Not Found');
        }


        //Unhandled Routes – Fallback Method for DELETE
        if ((Request::isMethod('delete') && $exception instanceof MethodNotAllowedHttpException) || (Request::isMethod('delete') && $exception instanceof NotFoundHttpException)) {

            return BaseController::sendError('Not Found');
        }



        //Override 404 ModelNotFoundException
        if ($exception instanceof ModelNotFoundException) {

            return BaseController::sendError('Entry for ' . str_replace('App\\', '', $exception->getModel()) . ' not found');
        }


        return parent::render($request, $exception);
    }
}
