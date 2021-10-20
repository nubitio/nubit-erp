<?php

namespace App\Domain\Common;

use JsonSerializable;

/**
 * Class ApiResponse
 */
class ApiResponse implements JsonSerializable
{
    /**
     * @var bool
     */
    private $success;

    /**
     * @var string
     */
    private $message;

    /**
     * @var mixed
     */
    private $data;

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'success' => $this->isSuccess(),
            'message' => $this->getMessage(),
            'data'    => $this->getData()
        ];
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @param bool $success
     * @return ApiResponse
     */
    public function setSuccess($success)
    {
        $this->success = $success;
        return $this;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     * @return ApiResponse
     */
    public function setMessage($message)
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return ApiResponse
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }
}