<?php

namespace App\WebClientPrint;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebClientPrintController extends BaseController
{
    /**
     * @throws \Exception
     */
    #[Route('/wcp', name: 'wcp', methods: ['GET'])]
    public function processRequest(Request $request): ?Response
    {
        $projectRoot = $this->getParameter('kernel.project_dir').'/src';

        WebClientPrint::$wcpCacheFolder = $projectRoot.'/WebClientPrint/wcpcache/';
        // ===================

        // Clean built-in Cache
        // NOTE: Remove it if you implement your own cache system
        WebClientPrint::cacheClean(30); // in minutes

        // get session id from querystring if any
        $sid = null;
        if ($request->query->has(WebClientPrint::SID)) {
            $sid = $request->query->get(WebClientPrint::SID);
        }

        // get query string from url
        $query = $request->getQueryString();
        // get request type
        $reqType = WebClientPrint::GetProcessRequestType($query);

        if (WebClientPrint::GenPrintScript == $reqType ||
            WebClientPrint::GenWcppDetectScript == $reqType) {
            // Let WebClientPrint to generate the requested script

            // Get Absolute URL of this file
            $currentAbsoluteURL = $request->getScheme().'://'.$request->getHttpHost().$request->getBaseUrl().$request->getPathInfo();

            // Return WebClientPrint's javascript code
            return new Response(WebClientPrint::generateScript($currentAbsoluteURL, $query), 200, ['Content-Type' => 'text/javascript']);
        } elseif (WebClientPrint::ClientSetWcppVersion == $reqType) {
            // This request is a ping from the WCPP utility
            // so store the session ID indicating this user has the WCPP installed
            // also store the WCPP Version if available
            if ($request->query->has(WebClientPrint::WCPP_SET_VERSION) && strlen($request->query->get(WebClientPrint::WCPP_SET_VERSION)) > 0) {
                WebClientPrint::cacheAdd($sid, WebClientPrint::WCP_CACHE_WCPP_VER, $request->query->get(WebClientPrint::WCPP_SET_VERSION));
            }

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientSetInstalledPrinters == $reqType) {
            // WCPP Utility is sending the installed printers at client side
            // so store this info with the specified session ID
            WebClientPrint::cacheAdd($sid, WebClientPrint::WCP_CACHE_PRINTERS, strlen($request->query->get(WebClientPrint::WCPP_SET_PRINTERS)) > 0 ? $request->query->get(WebClientPrint::WCPP_SET_PRINTERS) : '');

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientSetInstalledPrintersInfo == $reqType) {
            // WCPP Utility is sending the installed printers at client side with detailed info
            // so store this info with the specified session ID
            // Printers Info is in JSON format
            $printersInfo = $request->query->get('printersInfoContent');

            WebClientPrint::cacheAdd($sid, WebClientPrint::WCP_CACHE_PRINTERSINFO, $printersInfo);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientGetWcppVersion == $reqType) {
            // return the WCPP version for the specified Session ID (sid) if any
            return new Response(WebClientPrint::cacheGet($sid, WebClientPrint::WCP_CACHE_WCPP_VER), 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientGetInstalledPrinters == $reqType) {
            // return the installed printers for the specified Session ID (sid) if any
            return new Response(base64_decode(WebClientPrint::cacheGet($sid, WebClientPrint::WCP_CACHE_PRINTERS)), 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientGetInstalledPrintersInfo == $reqType) {
            // return the installed printers with detailed info for the specified Session ID (sid) if any
            return new Response(base64_decode(WebClientPrint::cacheGet($sid, WebClientPrint::WCP_CACHE_PRINTERSINFO)), 200, ['Content-Type' => 'text/plain']);
        } else {
            return new Response('Invalid Request', 400, ['Content-Type' => 'text/plain']);
        }
    }
}
