"""Run the audit harness in a new isolated application copy with synthetic data.

Requires a local compatible vendor directory, including Mockery. This does not
install dependencies, read production .env files, or contact external services.
"""

import argparse
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile


parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--php', type=Path, required=True)
parser.add_argument('--vendor', type=Path, required=True)
args = parser.parse_args()
php = args.php.resolve(strict=True)
vendor = args.vendor.resolve(strict=True)
if not (vendor / 'autoload.php').is_file():
    parser.error('--vendor must contain autoload.php')

source = Path(__file__).resolve().parents[2]
target = Path(tempfile.mkdtemp(prefix='tripcontrol-audit-'))
for directory in ['app', 'config', 'routes', 'resources', 'bootstrap']:
    shutil.copytree(source / directory, target / directory,
                    ignore=shutil.ignore_patterns('cache', '*.local.php'))
for directory in ['migrations', 'factories', 'seeders']:
    shutil.copytree(source / 'database' / directory,
                    target / 'database' / directory)
(target / 'bootstrap/cache').mkdir(parents=True)
for directory in ['framework/views', 'framework/sessions',
                  'framework/cache/data', 'logs', 'app/private', 'app/public']:
    (target / 'storage' / directory).mkdir(parents=True, exist_ok=True)
manifest = json.loads((source / 'composer.json').read_text())
manifest.setdefault('extra', {}).setdefault('laravel', {})['dont-discover'] = ['*']
(target / 'composer.json').write_text(json.dumps(manifest))
(target / 'vendor').symlink_to(vendor, target_is_directory=True)
shutil.copyfile(Path(__file__).with_name('review.php'), target / 'review.php')

# Deliberately exclude inherited application/database/API configuration.
environment = {'PATH': os.environ.get('PATH', ''), 'TMPDIR': str(target)}
result = subprocess.run([str(php), str(target / 'review.php')], cwd=target,
                        env=environment, capture_output=True, text=True)
print(result.stdout, end='')
if result.stderr:
    print(result.stderr, end='')
print(f'Isolated working copy: {target}')
if result.returncode == 0:
    try:
        data = json.loads(result.stdout)
    except json.JSONDecodeError as error:
        raise SystemExit(f'Harness did not return valid evidence JSON: {error}')
    (target / 'results.json').write_text(json.dumps(data, indent=2))
raise SystemExit(result.returncode)
