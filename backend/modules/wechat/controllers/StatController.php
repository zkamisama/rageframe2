<?php
namespace backend\modules\wechat\controllers;

use yii;
use yii\data\Pagination;
use yii\helpers\ArrayHelper;
use common\models\wechat\RuleStat;
use common\models\wechat\RuleKeywordStat;
use common\models\wechat\FansStat;

/**
 * 数据统计
 * 
 * Class StatController
 * @package backend\modules\wechat\controllers
 */
class StatController extends WController
{
    /**
     * 默认关注数据
     *
     * @var array
     */
    public $attention = [
        'new_attention' => 0,
        'cancel_attention' => 0,
        'increase_attention' => 0,
        'cumulate_attention' => 0,
    ];

    /**
     * 粉丝关注统计
     *
     * @return string
     */
    public function actionFansFollow()
    {
        // 更新微信统计数据
        FansStat::getFansStat();

        $request  = Yii::$app->request;
        $from_date  = $request->get('from_date', date('Y-m-d', strtotime("-6 day")));
        $to_date  = $request->get('to_date',date('Y-m-d'));

        $models = FansStat::find()
            ->where(['between', 'created_at', strtotime($from_date), strtotime($to_date)])
            ->orderBy('created_at asc')
            ->asArray()
            ->all();

        // 昨日关注
        $yesterdayModel = FansStat::find()
            ->where(['created_at' => strtotime(date('Y-m-d')) - 60*60*24])
            ->asArray()
            ->one();

        $yesterday =  $this->attention;
        if($yesterdayModel)
        {
            $yesterday = ArrayHelper::merge($this->attention, $yesterdayModel);
            $yesterday['increase_attention'] = $yesterday['new_attention'] - $yesterday['cancel_attention'];
        }

        // 今日关注
        $todayModel = FansStat::find()
            ->where(['created_at' => strtotime(date('Y-m-d'))])
            ->asArray()
            ->one();

        $today = $this->attention;
        if($todayModel)
        {
            $today = ArrayHelper::merge($this->attention, $todayModel);
            $today['increase_attention'] = $today['new_attention'] - $today['cancel_attention'];
        }

        $stat = [];
        foreach ($models as &$model)
        {
            $stat[$model['date']] = $model;
        }

        $fansStat = [];
        for ($i = strtotime($from_date); $i <= strtotime($to_date); $i += 86400)
        {
            $day = date('Y-m-d', $i);
            if(isset($stat[$day]))
            {
                $fansStat['new_attention'][] = $stat[$day]['new_attention'];
                $fansStat['cancel_attention'][] = $stat[$day]['cancel_attention'];
                $fansStat['increase_attention'][] = $stat[$day]['new_attention'] - $stat[$day]['cancel_attention'];
                $fansStat['cumulate_attention'][] = $stat[$day]['cumulate_attention'];
            }
            else
            {
                $fansStat['new_attention'][] = 0;
                $fansStat['cancel_attention'][] = 0;
                $fansStat['increase_attention'][] = 0;
                $fansStat['cumulate_attention'][] = 0;
            }

            $fansStat['chartTime'][] = $day;
        }

        return $this->render('fans-follow',[
            'models' => $models,
            'yesterday' => $yesterday,
            'today' => $today,
            'fansStat' => $fansStat,
            'from_date' => $from_date,
            'to_date' => $to_date,
        ]);
    }

    /**
     * 回复规则统计
     *
     * @return string
     */
    public function actionRule()
    {
        $request  = Yii::$app->request;
        $from_date  = $request->get('from_date', date('Y-m-d',strtotime("-60 day")));
        $to_date  = $request->get('to_date', date('Y-m-d',strtotime("+1 day")));

        $data = RuleStat::find()
            ->select(['rule_id','sum(hit) as hit','max(updated_at) as updated_at'])
            ->groupBy(['rule_id'])
            ->where(['between','created_at',strtotime($from_date),strtotime($to_date)]);

        $pages = new Pagination(['totalCount' => $data->count(), 'pageSize' => $this->_pageSize]);
        $models = $data->offset($pages->offset)
            ->with('rule')
            ->orderBy('updated_at desc')
            ->limit($pages->limit)
            ->all();

        return $this->render('rule',[
            'models' => $models,
            'pages' => $pages,
            'from_date' => $from_date,
            'to_date' => $to_date,
        ]);
    }

    /**
     * 回复规则统计
     *
     * @return string
     */
    public function actionRuleKeyword()
    {
        $request  = Yii::$app->request;
        $from_date  = $request->get('from_date', date('Y-m-d', strtotime("-60 day")));
        $to_date  = $request->get('to_date', date('Y-m-d', strtotime("+1 day")));

        $data = RuleKeywordStat::find()
            ->select(['keyword_id','sum(hit) as hit','max(rule_id) as rule_id','max(updated_at) as updated_at'])
            ->groupBy(['keyword_id'])
            ->where(['between', 'created_at', strtotime($from_date), strtotime($to_date)]);

        $pages = new Pagination(['totalCount' => $data->count(), 'pageSize' => $this->_pageSize]);
        $models = $data->offset($pages->offset)
            ->with(['rule','ruleKeyword'])
            ->orderBy('updated_at desc')
            ->limit($pages->limit)
            ->all();

        return $this->render('rule-keyword',[
            'models' => $models,
            'pages' => $pages,
            'from_date' => $from_date,
            'to_date' => $to_date,
        ]);
    }
}