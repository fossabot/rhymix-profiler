<?php
/* Copyright (C) NAVER <http://www.navercorp.com> */

/**
 * @class  profilerController
 * @author NAVER (developers@xpressengine.com)
 * @brief  Profiler module controller class.
 */

class profilerController extends profiler
{
	function init()
	{
	}

	/**
	 * @brief Slowlog 기록
	 * @param stdClass $args
	 * @return mixed
	 */
	function triggerAfterDisplay()
	{
		$oProfilerModel = getModel('profiler');
		$config = $oProfilerModel->getConfig();

		// 슬로우 로그를 쓰지 않을경우 리턴
		if($config->slowlog->enabled !== 'Y')
		{
			return new Object();
		}

		$oDB = DB::getInstance();

		$oDB->begin();
		$triggers = Rhymix\Framework\Debug::getSlowTriggers();

		foreach($triggers as $val)
		{
			self::insertSlowLog($val);
		}

		$widgets = Rhymix\Framework\Debug::getSlowWidgets();

		foreach($widgets as $val)
		{
			self::insertSlowLog($val);
		}

		$oDB->commit();
	}

	protected static function insertSlowLog($val)
	{
		// hash id 생성
		if($val->type == 'trigger')
		{
			$type_hash_id = md5($val->trigger_name . '@' . $val->trigger_target);
			$caller = $val->trigger_name;
			$called = $val->trigger_target;
			$called_extension = $val->trigger_plugin;
			$slow_time = $val->trigger_time;
		}
		else
		{
			$caller = 'widget.execute';
			$type_hash_id = md5($caller . '@' . $val->widget_name);
			$called = $val->widget_name;
			$called_extension = $val->widget_name;
			$slow_time = $val->widget_time;
		}

		// type 에 등록 여부 확인
		$hash_args = new stdClass();
		$hash_args->hash_id = $type_hash_id;
		$output = executeQuery('profiler.getSlowlogType', $hash_args);

		// type 등록이 안되어있으면 등록
		if(!$output->data)
		{
			$slowlog_type = new stdClass();
			$slowlog_type->type = $val->type;
			$slowlog_type->hash_id = $type_hash_id;
			$slowlog_type->caller = $caller;
			$slowlog_type->called = $called;
			$slowlog_type->called_extension = $called_extension;
			$output_type = executeQuery('profiler.insertSlowlogType', $slowlog_type);
			if(!$output_type->toBool())
			{
				return $output_type;
			}
		}

		$slowlog = new stdClass();
		$slowlog->type_hash_id = $type_hash_id;
		$slowlog->elapsed_time = $slow_time;
		$slowlog->logged_timestamp = time();
		$output_log = executeQuery('profiler.insertSlowlog', $slowlog);
		if(!$output_log->toBool())
		{
			return $output_log;
		}
	}
}

/* End of file profiler.controller.php */
/* Location: ./modules/profiler/profiler.controller.php */
