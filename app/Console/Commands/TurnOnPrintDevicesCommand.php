<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TurnOnPrintDevicesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ds:turn-on:print';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Turn on all of print devices.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (cache('ds:on-print:done', false)) {
            return;
        }

        $start = now()->setTime(6, 20, 0);
        $end = now()->setTime(23, 0, 0);
        if (now()->between($start, $end)) {
            $this->info('正在开启打印数据集群...');
            $count = $this->open([
                'clusters.1' => 'cl-kk3cefa2',
                'clusters.2' => 'cl-iyqnwwzw',
            ]);

            if (!$count) {
                cache()->put('ds:on-print:done', true, now()->addDay()->setTime(6, 10));
            }
        } else {
            $this->info('当前处于停机时段，禁止开机。');
        }
    }

    public function open($clusters)
    {
        $count = 0;

        $url = QCHelper::getApiUrl('DescribeClusters', $clusters);
        $client = new \GuzzleHttp\Client(['timeout' => 0]);
        $ret = $client->get($url);
        $clustersRet = json_decode($ret->getBody()->getContents(), true);
        if ($clustersRet && !$clustersRet['ret_code'] &&
            isset($clustersRet['cluster_set']) && !empty($clustersRet['cluster_set'])) {
            $needStops = [];
            $sIdx = 1;
            foreach ($clustersRet['cluster_set'] as $cluster) {
                if ($cluster['status'] != 'active') {
                    $needStops['clusters.' . ($sIdx++)] = $cluster['cluster_id'];
                }
            }

            if (!empty($needStops)) {
                $count = count($needStops);
                $url = QCHelper::getApiUrl('StartClusters', $needStops);
                $client = new \GuzzleHttp\Client(['timeout' => 0]);
                $ret = $client->get($url);
                $ret = json_decode($ret->getBody()->getContents(), true);
                if ($ret && !$ret['ret_code']) {
                    $this->info($count . ' clusters is starting.');
                } else {
                    $this->info('Stop clusters err(' . (print_r($ret, true)) . ').');
                }
            } else {
                $this->info('All cluster is started.');
            }
        } else {
            $count = -1;
            $this->info('Check clusters status fail.');
        }

        return $count;
    }
}
