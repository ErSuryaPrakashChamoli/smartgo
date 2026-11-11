import glob, re, sys, xml.etree.ElementTree as ET
from collections import OrderedDict
S = sys.argv[1]
streams = OrderedDict([
    ('P9-D deals (OD-170, in progress)', r'Crm/DealPipelineTest'),
    ('P9-C first-response timers (OD-170)', r'FirstResponse|Services/Crm/'),
    ('P9-B assignment rules (OD-159..OD-163)', r'Assignment'),
    ('Phase 9 other CRM (enquiries, tours, contacts)', r'Crm/|Sales/|Leads|Lead[A-Z]|Tour|Enquir|Contact'),
    ('OD-169 home banners, highlights, header', r'HomeShowcase'),
    ('OD-168 menus + OD-169 header (shared shell)', r'PublicShellTest'),
    ('OD-165/166 seeders and access sync', r'Seeder|SyncAccessControl'),
    ('OD-164 harness guard', r'HarnessDatabaseGuard'),
    ('Design previews (permissions review)', r'DesignPreview'),
    ('Phases 2–8, 11–14: everything else', r'.'),
])
parts = ['part1-actions', 'part2-console-to-arch', 'part3-infrastructure', 'part4-filament', 'part5-http', 'part6-services-views']
stats = OrderedDict((k, {'files': 0, 'tests': 0, 'failures': 0, 'failed': []}) for k in streams)
total = {'tests': 0, 'assertions': 0, 'failures': 0}
for part in parts:
    root = ET.parse(f'{S}/{part}.xml').getroot()
    top = root.find('testsuite')
    for k in total:
        total[k] += int(top.get(k, 0))
    def files(node):
        for child in node.findall('testsuite'):
            f = child.get('file')
            if f and f.endswith('.php'):
                yield child
            else:
                yield from files(child)
    for ts in files(root):
        f = ts.get('file')
        name = f.split('tests/')[-1]
        stream = next(k for k, rx in streams.items() if re.search(rx, name))
        s = stats[stream]
        s['files'] += 1; s['tests'] += int(ts.get('tests')); s['failures'] += int(ts.get('failures')) + int(ts.get('errors'))
        for tc in ts.iter('testcase'):
            if tc.find('failure') is not None or tc.find('error') is not None:
                s['failed'].append(f"{part}: {name} :: {tc.get('name')[:90]}")
print('six parts:', total)
for k, s in stats.items():
    print(f"{k}: {s['files']} files, {s['tests']} tests, {s['failures']} failed")
    for x in s['failed']:
        print('    -', x)
