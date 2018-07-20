<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use QCloud\QCHelper;

class TurnOffDataServersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ds:turn-off';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Turn off all of data servers.';

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
        if (cache('ds:off:done', false)) {
            return;
        }
        $start = now()->setTime(0, 05, 0);
        $end = now()->setTime(6, 0, 0);
        if (now()->between($start, $end)) {
            $this->info('正在关闭Mysql plus cluster...');
            $count = $this->close([
                'clusters.1' => 'cl-qj2wgid5',
                'clusters.2' => 'cl-3lbo6w7k',
                'clusters.3' => 'cl-u1cnzupi',
                'clusters.4' => 'cl-oevzzcwq',
                'clusters.5' => 'cl-ivd75fht',
                'clusters.6' => 'cl-kk3cefa2',
                'clusters.7' => 'cl-ozbdd2tg',
                'clusters.8' => 'cl-nvhcr6fd',
            ]);

            if (!$count) {
                $this->info('正在关闭Zookeeper cluster...');
                $count = $this->close([
                    'clusters.1' => 'cl-iyqnwwzw'
                ]);
                if (!$count) {
                    cache()->put('ds:off:done', true, now()->endOfDay());
                }
            }
        } else {
            $this->info('当前处于开机时段，禁止开机。');
        }
    }

    public function close($clusters)
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
                if ($cluster['status'] == 'active') {
                    $needStops['clusters.' . ($sIdx++)] = $cluster['cluster_id'];
                }
            }

            if (!empty($needStops)) {
                $count = count($needStops);
                $url = QCHelper::getApiUrl('StopClusters', $needStops);
                $client = new \GuzzleHttp\Client(['timeout' => 0]);
                $ret = $client->get($url);
                $ret = json_decode($ret->getBody()->getContents(), true);
                if ($ret && !$ret['ret_code']) {
                    $this->info($count . ' clusters is stopping.');
                } else {
                    $this->info('Stop clusters err(' . (print_r($ret, true)) . ').');
                }
            } else {
                $this->info('All cluster is stopped.');
            }
        } else {
            $count = -1;
            $this->info('Check clusters status fail.');
        }

        return $count;
    }
}
