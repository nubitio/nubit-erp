<?php

namespace App\WebClientPrint;

use App\Base\BaseController;
use App\Core\Infrastructure\Service\CacheManager;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebClientPrintController extends BaseController
{
    /**
     * @throws \Exception
     * @throws InvalidArgumentException
     */
    #[Route('/wcp', name: 'wcp', methods: ['GET'])]
    public function processRequest(Request $request, CacheManager $cache): ?Response
    {
        $query = $request->getQueryString();
        $reqType = WebClientPrint::GetProcessRequestType($query);

        if (WebClientPrint::GenPrintScript == $reqType || WebClientPrint::GenWcppDetectScript == $reqType) {
            $currentAbsoluteURL = $request->getScheme().'://'.$request->getHttpHost().$request->getBaseUrl().$request->getPathInfo();

            return new Response(WebClientPrint::generateScript($currentAbsoluteURL, $query), 200, ['Content-Type' => 'text/javascript']);
        } elseif (WebClientPrint::ClientSetWcppVersion == $reqType) {
            if ($request->query->has(WebClientPrint::WCPP_SET_VERSION) && strlen($request->query->get(WebClientPrint::WCPP_SET_VERSION)) > 0) {
                $cache->set(WebClientPrint::WCP_CACHE_WCPP_VER, $request->query->get(WebClientPrint::WCPP_SET_VERSION, 3600));
            }

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientSetInstalledPrinters == $reqType) {
            $printers = strlen($request->query->get(WebClientPrint::WCPP_SET_PRINTERS)) > 0 ? $request->query->get(WebClientPrint::WCPP_SET_PRINTERS) : '';
            $cache->set(WebClientPrint::WCP_CACHE_PRINTERS, $printers, 3600);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientSetInstalledPrintersInfo == $reqType) {
            $printersInfo = $request->query->get('printersInfoContent');

            $cache->set(WebClientPrint::WCP_CACHE_PRINTERSINFO, $printersInfo, 3600);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientGetWcppVersion == $reqType) {
            $content = $cache->get(WebClientPrint::WCP_CACHE_WCPP_VER);

            return new Response($content, 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientGetInstalledPrinters == $reqType) {
            $content = $cache->get(WebClientPrint::WCP_CACHE_PRINTERS);

            return new Response(base64_decode($content), 200, ['Content-Type' => 'text/plain']);
        } elseif (WebClientPrint::ClientGetInstalledPrintersInfo == $reqType) {
            $content = $cache->get(WebClientPrint::WCP_CACHE_PRINTERSINFO);

            return new Response(base64_decode($content), 200, ['Content-Type' => 'text/plain']);
        } else {
            return new Response('Invalid Request', 400, ['Content-Type' => 'text/plain']);
        }
    }
}
