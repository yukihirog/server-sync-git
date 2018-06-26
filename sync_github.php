<?php

// フォルダの展開先
// apache:apacheなど、wwwユーザーの権限を付与しておくこと
$TARGET_PATH = '/var/www/html/';

// GIT_SSHで鍵指定する場合はここで設定してshファイル内に鍵ファイルの場所を記述する
// 別の方法で鍵指定できている場合は''にする
$GIT_SSH = 'GIT_SSH=' . dirname(__FILE__) . '/git-ssh.sh';

// GitHubのWebhookで設定するSecret
$SECRET_KEY = 'SECRET_KEY';

echo "check SECRET_KEY\n";
if ($SECRET_KEY) {
	$header = getallheaders();
	$hmac = hash_hmac('sha1', file_get_contents('php://input'), $SECRET_KEY);

	if (!isset($header['X-Hub-Signature']) || $header['X-Hub-Signature'] !== 'sha1='.$hmac) {
		http_response_code(400);
		echo "Failed: Invalid secret key.\n";
		exit(1);
	}
}
echo "check SECRET_KEY: ok\n";

function remove_directory($dir) {
	$flag = TRUE;
	if ($handle = opendir("$dir")) {
		while (false !== ($item = readdir($handle))) {
			if ($item != "." && $item != "..") {
				if (is_dir("$dir/$item")) {
					$flag = $flag && remove_directory("$dir/$item");
				} else {
					$flag = $flag && unlink("$dir/$item");
				}
			}
		}

		closedir($handle);
		$flag = $flag && rmdir($dir);
		return $flag;
	}
}

echo "check payload\n";
if (isset($_POST['payload']) ) {
	echo "check payload: ok\n";

	$payload = json_decode($_POST['payload'], true);

	echo "check ref\n";
	if (preg_match('#^refs/heads/(.+)#', $payload['ref'], $matches)) {
		echo "check ref: ok\n";

		$repo            = $payload['repository']['ssh_url'];
		$project         = $payload['repository']['name'];
		$project_dirname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $project));
		$branch          = $matches[1];
		$branch_dirname  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $branch));
		$project_dir     = $TARGET_PATH . $project_dirname;
		$branch_dir      = $project_dir . '/' . $branch_dirname;

		echo "check delete\n";
		if ($payload['deleted']) {
			echo "check delete: is delete\n";

			// 削除フラグがある場合
			echo "check branch directory\n";
			if (file_exists($branch_dir)) {
				echo "check branch directory: exists\n";

				echo "remove branch directory\n";
				if (remove_directory($branch_dir)) {
					echo "remove branch directory: ok\n";
					echo "Remove Branch directory.\n";
				} else {
					echo "Failed: Remove Branch directory.\n";
					exit(1);
				}
			} else {
				echo "No Branch directory.\n";
			}
		} else {
			echo "check delete: is update\n";
			// 更新

			// プロジェクトディレクトリの作成
			echo "check project directory\n";
			if (!file_exists($project_dir)) {
				echo "check project directory: no project directory\n";

				echo "create project directory\n";
				if (mkdir($project_dir, 0777)) {
					echo "create project directory: ok\n";
					echo "Create Project directory.\n";
				} else {
					echo "Failed: Create Project directory.\n";
					exit(1);
				}
			} else {
				echo "check project directory: exists\n";
			}

			echo "check branch directory\n";
			if (file_exists($branch_dir)) {
				echo "check branch directory: exists\n";

				// ブランチディレクトリがある場合はpull
				$cmd = 'cd ' . $branch_dir . '; ' . $GIT_SSH . ' git pull';
				exec($cmd, $output);
				var_dump($output);
				echo "Pulled.\n";
				echo "Project: " . $project . "\n";
				echo "branch: " . $branch . "\n";
			} else {
				echo "check branch directory: no branch directory\n";

				// ブランチディレクトリがなければclone
				// githubのデプロイキーの指定にGIT_SSH=***.shを経由して鍵指定している
				echo "create branch directory\n";
				if (mkdir($branch_dir, 0777)) {
					echo "create branch directory: ok\n";

					$cmd = 'cd ' . $project_dir . '; ' . $GIT_SSH . ' git clone -b ' . $branch . ' --single-branch ' . $repo . ' ' . $branch_dir;
					exec($cmd, $output);
					var_dump($output);
					echo "Clone repository.\n";
					echo "Project: " . $project . "\n";
					echo "branch: " . $branch . "\n";
				} else {
					echo "Failed: Create Branch directory.\n";
					exit(1);
				}
			}
		}
	} else {
		echo "check refs: none\n";
		var_dump($payload);
	}
} else {
	echo "Failed: Empty payload.\n";
	exit(1);
}

?>