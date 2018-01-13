<?php

namespace ccxt;

class coinspot extends Exchange {

    public function describe () {
        return array_replace_recursive (parent::describe (), array (
            'id' => 'coinspot',
            'name' => 'CoinSpot',
            'countries' => 'AU', // Australia
            'rateLimit' => 1000,
            'hasCORS' => false,
            'urls' => array (
                'logo' => 'https://user-images.githubusercontent.com/1294454/28208429-3cacdf9a-6896-11e7-854e-4c79a772a30f.jpg',
                'api' => array (
                    'public' => 'https://www.coinspot.com.au/pubapi',
                    'private' => 'https://www.coinspot.com.au/api',
                ),
                'www' => 'https://www.coinspot.com.au',
                'doc' => 'https://www.coinspot.com.au/api',
            ),
            'api' => array (
                'public' => array (
                    'get' => array (
                        'latest',
                    ),
                ),
                'private' => array (
                    'post' => array (
                        'orders',
                        'orders/history',
                        'my/coin/deposit',
                        'my/coin/send',
                        'quote/buy',
                        'quote/sell',
                        'my/balances',
                        'my/orders',
                        'my/buy',
                        'my/sell',
                        'my/buy/cancel',
                        'my/sell/cancel',
                    ),
                ),
            ),
            'markets' => array (
                'BTC/AUD' => array ( 'id' => 'BTC', 'symbol' => 'BTC/AUD', 'base' => 'BTC', 'quote' => 'AUD' ),
                'LTC/AUD' => array ( 'id' => 'LTC', 'symbol' => 'LTC/AUD', 'base' => 'LTC', 'quote' => 'AUD' ),
                'DOGE/AUD' => array ( 'id' => 'DOGE', 'symbol' => 'DOGE/AUD', 'base' => 'DOGE', 'quote' => 'AUD' ),
            ),
        ));
    }

    public function fetch_balance ($params = array ()) {
        $response = $this->privatePostMyBalances ();
        $result = array ( 'info' => $response );
        if (is_array ($response) && array_key_exists ('balance', $response)) {
            $balances = $response['balance'];
            $currencies = array_keys ($balances);
            for ($c = 0; $c < count ($currencies); $c++) {
                $currency = $currencies[$c];
                $uppercase = strtoupper ($currency);
                $account = array (
                    'free' => $balances[$currency],
                    'used' => 0.0,
                    'total' => $balances[$currency],
                );
                if ($uppercase == 'DRK')
                    $uppercase = 'DASH';
                $result[$uppercase] = $account;
            }
        }
        return $this->parse_balance($result);
    }

    public function fetch_order_book ($symbol, $params = array ()) {
        $market = $this->market ($symbol);
        $orderbook = $this->privatePostOrders (array_merge (array (
            'cointype' => $market['id'],
        ), $params));
        $result = $this->parse_order_book($orderbook, null, 'buyorders', 'sellorders', 'rate', 'amount');
        $result['bids'] = $this->sort_by($result['bids'], 0, true);
        $result['asks'] = $this->sort_by($result['asks'], 0);
        return $result;
    }

    public function fetch_ticker ($symbol, $params = array ()) {
        $response = $this->publicGetLatest ($params);
        $id = $this->market_id($symbol);
        $id = strtolower ($id);
        $ticker = $response['prices'][$id];
        $timestamp = $this->milliseconds ();
        return array (
            'symbol' => $symbol,
            'timestamp' => $timestamp,
            'datetime' => $this->iso8601 ($timestamp),
            'high' => null,
            'low' => null,
            'bid' => floatval ($ticker['bid']),
            'ask' => floatval ($ticker['ask']),
            'vwap' => null,
            'open' => null,
            'close' => null,
            'first' => null,
            'last' => floatval ($ticker['last']),
            'change' => null,
            'percentage' => null,
            'average' => null,
            'baseVolume' => null,
            'quoteVolume' => null,
            'info' => $ticker,
        );
    }

    public function fetch_trades ($symbol, $since = null, $limit = null, $params = array ()) {
        return $this->privatePostOrdersHistory (array_merge (array (
            'cointype' => $this->market_id($symbol),
        ), $params));
    }

    public function create_order ($market, $type, $side, $amount, $price = null, $params = array ()) {
        $method = 'privatePostMy' . $this->capitalize ($side);
        if ($type == 'market')
            throw new ExchangeError ($this->id . ' allows limit orders only');
        $order = array (
            'cointype' => $this->market_id($market),
            'amount' => $amount,
            'rate' => $price,
        );
        return $this->$method (array_merge ($order, $params));
    }

    public function cancel_order ($id, $symbol = null, $params = array ()) {
        throw new ExchangeError ($this->id . ' cancelOrder () is not fully implemented yet');
        $method = 'privatePostMyBuy';
        return $this->$method (array ( 'id' => $id ));
    }

    public function sign ($path, $api = 'public', $method = 'GET', $params = array (), $headers = null, $body = null) {
        if (!$this->apiKey)
            throw new AuthenticationError ($this->id . ' requires apiKey for all requests');
        $url = $this->urls['api'][$api] . '/' . $path;
        if ($api == 'private') {
            $this->check_required_credentials();
            $nonce = $this->nonce ();
            $body = $this->json (array_merge (array ( 'nonce' => $nonce ), $params));
            $headers = array (
                'Content-Type' => 'application/json',
                'key' => $this->apiKey,
                'sign' => $this->hmac ($this->encode ($body), $this->encode ($this->secret), 'sha512'),
            );
        }
        return array ( 'url' => $url, 'method' => $method, 'body' => $body, 'headers' => $headers );
    }
}