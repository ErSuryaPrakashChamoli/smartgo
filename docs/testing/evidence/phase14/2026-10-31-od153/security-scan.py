import re, subprocess, glob, os, datetime
print('OD-153 security scan', datetime.datetime.now().isoformat(timespec='seconds'), '(values never printed)')
env = {}
for line in open('.env'):
    m = re.match(r'^([A-Z_]+)=(.*)$', line.rstrip('\n'))
    if m:
        env[m.group(1)] = m.group(2).strip('"\'')
sec = {k: v for k, v in env.items() if len(v) >= 8 and re.search(r'(PASSWORD|KEY|SECRET|TOKEN)', k)}
st = os.stat('.env')
print('.env mode', oct(st.st_mode)[-3:], '(was 664; changed to 600 in OD-153), owner uid', st.st_uid)
revs = subprocess.run(['git', 'rev-list', '--all'], capture_output=True, text=True).stdout.split()
print('commits scanned', len(revs))
for k, v in sec.items():
    h = subprocess.run(['git', 'grep', '-F', '-l', '-e', v] + revs, capture_output=True, text=True).stdout.strip().splitlines()
    t = subprocess.run(['git', 'grep', '--untracked', '-F', '-l', '-e', v], capture_output=True, text=True).stdout.strip().splitlines()
    print(f'{k}: history {len(h)}, working tree (tracked + untracked, not ignored) {len(t)}')
print('.env ever committed:', bool(subprocess.run(['git', 'log', '--all', '--oneline', '--', '.env'], capture_output=True, text=True).stdout.strip()), '| ignored:', subprocess.run(['git', 'check-ignore', '-q', '.env']).returncode == 0)
pattern = r'(APP_KEY=base64:[A-Za-z0-9+/=]{20,}|sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|-----BEGIN (RSA |EC )?PRIVATE KEY-----|ghp_[A-Za-z0-9]{30,})'
pat = subprocess.run(['git', 'grep', '--untracked', '-l', '-I', '-E', pattern], capture_output=True, text=True).stdout.strip().splitlines()
print('generic secret patterns:', len(pat))
password_field = re.compile(r'"password"\s*[:=]\s*"[^"*]{3,}')
logs = glob.glob('storage/logs/*.log') + glob.glob('/home/administrator/smartgro-demo/storage/logs/*.log') + ['/home/administrator/smartgro-demo/server.log', '/home/administrator/smartgro-site/server.log'] + glob.glob('storage/app/private/preview-storage/logs/*.log')
for f in logs:
    if os.path.exists(f):
        s = open(f, errors='replace').read()
        print(f'log {f}: secret values {sum(s.count(v) for v in sec.values())}, password fields with values {len(password_field.findall(s))}, mode {oct(os.stat(f).st_mode)[-3:]}')
print('APP_DEBUG in .env:', env.get('APP_DEBUG'), '(local server bound to 127.0.0.1; DEPLOYMENT_RUNBOOK requires false in production)')
