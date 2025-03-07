<?php

declare(strict_types=1);

namespace Imi\Util\Http;

use Imi\Util\Http\Consts\RequestMethod;
use Imi\Util\Http\Contract\IRequest;
use Imi\Util\Uri;
use Psr\Http\Message\UriInterface;

class Request extends AbstractMessage implements IRequest
{
    /**
     * 请求地址
     */
    protected UriInterface $uri;

    /**
     * 请求方法.
     */
    protected string $method = RequestMethod::GET;

    /**
     * uri 是否初始化.
     */
    protected bool $uriInited = false;

    /**
     * method 是否初始化.
     */
    protected bool $methodInited = false;

    /**
     * Retrieves the message's request target.
     *
     * Retrieves the message's request-target either as it will appear (for
     * clients), as it appeared at request (for servers), or as it was
     * specified for the instance (see withRequestTarget()).
     *
     * In most cases, this will be the origin-form of the composed URI,
     * unless a value was provided to the concrete implementation (see
     * withRequestTarget() below).
     *
     * If no URI is available, and no request-target has been specifically
     * provided, this method MUST return the string "/".
     *
     * @return string
     */
    public function getRequestTarget()
    {
        return (string) $this->uri;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request target.
     *
     * @see http://tools.ietf.org/html/rfc7230#section-2.7 (for the various
     *     request-target forms allowed in request messages)
     *
     * @param mixed $requestTarget
     *
     * @return static
     */
    public function withRequestTarget($requestTarget)
    {
        $self = clone $this;
        $self->withUri(new Uri($requestTarget));

        return $self;
    }

    /**
     * Return an instance with the specific request-target.
     *
     * If the request needs a non-origin-form request-target — e.g., for
     * specifying an absolute-form, authority-form, or asterisk-form —
     * this method may be used to create an instance with the specified
     * request-target, verbatim.
     *
     * @see http://tools.ietf.org/html/rfc7230#section-5.3 (for the various
     *     request-target forms allowed in request messages)
     *
     * @param mixed $requestTarget
     *
     * @return static
     */
    public function setRequestTarget($requestTarget): self
    {
        $this->setUri(new Uri($requestTarget));

        return $this;
    }

    /**
     * 初始化 method.
     */
    protected function initMethod(): void
    {
    }

    /**
     * Retrieves the HTTP method of the request.
     *
     * @return string returns the request method
     */
    public function getMethod()
    {
        if (!$this->methodInited)
        {
            $this->initMethod();
            $this->methodInited = true;
        }

        return $this->method;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * changed request method.
     *
     * @param string $method case-sensitive method
     *
     * @return static
     *
     * @throws \InvalidArgumentException for invalid HTTP methods
     */
    public function withMethod($method)
    {
        $self = clone $this;
        $self->method = $method;
        $self->methodInited = true;

        return $self;
    }

    /**
     * Return an instance with the provided HTTP method.
     *
     * While HTTP method names are typically all uppercase characters, HTTP
     * method names are case-sensitive and thus implementations SHOULD NOT
     * modify the given string.
     *
     * @param string $method case-sensitive method
     *
     * @return static
     *
     * @throws \InvalidArgumentException for invalid HTTP methods
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;
        $this->methodInited = true;

        return $this;
    }

    /**
     * 初始化 uri.
     */
    protected function initUri(): void
    {
    }

    /**
     * Retrieves the URI instance.
     *
     * This method MUST return a UriInterface instance.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @return UriInterface returns a UriInterface instance
     *                      representing the URI of the request
     */
    public function getUri()
    {
        if (!$this->uriInited)
        {
            $this->initUri();
            $this->uriInited = true;
        }

        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * new UriInterface instance.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @param UriInterface $uri          new request URI to use
     * @param bool         $preserveHost preserve the original state of the Host header
     *
     * @return static
     */
    public function withUri(UriInterface $uri, $preserveHost = false)
    {
        $self = clone $this;

        return $self->setUri($uri, $preserveHost);
    }

    /**
     * Returns an instance with the provided URI.
     *
     * This method MUST update the Host header of the returned request by
     * default if the URI contains a host component. If the URI does not
     * contain a host component, any pre-existing Host header MUST be carried
     * over to the returned request.
     *
     * You can opt-in to preserving the original state of the Host header by
     * setting `$preserveHost` to `true`. When `$preserveHost` is set to
     * `true`, this method interacts with the Host header in the following ways:
     *
     * - If the Host header is missing or empty, and the new URI contains
     *   a host component, this method MUST update the Host header in the returned
     *   request.
     * - If the Host header is missing or empty, and the new URI does not contain a
     *   host component, this method MUST NOT update the Host header in the returned
     *   request.
     * - If a Host header is present and non-empty, this method MUST NOT update
     *   the Host header in the returned request.
     *
     * @see http://tools.ietf.org/html/rfc3986#section-4.3
     *
     * @param UriInterface $uri          new request URI to use
     * @param bool         $preserveHost preserve the original state of the Host header
     *
     * @return static
     */
    public function setUri(UriInterface $uri, bool $preserveHost = false): self
    {
        if (!$this->uriInited)
        {
            $this->initUri();
            $this->uriInited = true;
        }
        $this->uri = $uri;
        if (!$preserveHost)
        {
            $this->headers = [];
            $this->headerNames = [];
            $this->headersInited = true;
        }

        return $this;
    }
}
