<?php
class Main
{
	public function index(View $view)
	{
		print $view->render('main');
	}
}