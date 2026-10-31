"""OD-151 sitemap audit: python3 sitemap_audit.py <port> <database> <sitemap.xml>
Validates the XML against the sitemaps.org 0.9 structure, fetches every listed address (GET, the local server only),
and compares the list with the database's published content (read-only queries through ro/q.php)."""
import json, re, subprocess, sys, urllib.request, urllib.error
import xml.etree.ElementTree as ET

port, database, path = sys.argv[1], sys.argv[2], sys.argv[3]
base = f'http://127.0.0.1:{port}'
NS = '{http://www.sitemaps.org/schemas/sitemap/0.9}'
Q = '/tmp/claude-1000/-home-administrator-Documents-website-smartGrow/fa6d3911-576e-4c69-af44-fe7acebc926f/scratchpad/ro/q.php'
report = {'port': port, 'database': database, 'problems': []}
problem = report['problems'].append


def rows(sql):
    out = subprocess.run(['php', Q, database, sql], capture_output=True, text=True, check=True).stdout
    return [line.split(' | ') for line in out.splitlines() if line]


raw = open(path, 'rb').read()
root = ET.fromstring(raw)  # raises on malformed XML
report['well_formed'] = True
report['root'] = root.tag
if root.tag != NS + 'urlset':
    problem(f'root element {root.tag}')
locs = []
for url in root:
    if url.tag != NS + 'url':
        problem(f'unexpected element {url.tag}')
    children = {child.tag.replace(NS, ''): (child.text or '') for child in url}
    if set(children) - {'loc', 'lastmod', 'changefreq', 'priority'} or 'loc' not in children:
        problem(f'url children {sorted(children)}')
    if 'lastmod' in children and not re.fullmatch(r'\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z))?', children['lastmod']):
        problem(f'lastmod {children["lastmod"]}')
    locs.append(children.get('loc', ''))
report['urls'] = len(locs)
report['duplicates'] = sorted({loc for loc in locs if locs.count(loc) > 1})
if report['duplicates']:
    problem(f'duplicates {report["duplicates"]}')
if len(locs) > 50000 or len(raw) > 50 * 1024 * 1024:
    problem('too large')
if re.search(rb'(?i)exception|whoops|stack trace|sqlstate|<html|warning:|notice:|deprecated', raw):
    problem('error or debug text in the response')

paths = []
for loc in locs:
    if not loc.startswith(base + '/') and loc != base + '/':
        problem(f'other host {loc}')
    paths.append(loc[len(base):] or '/')

report['checked'] = []
for p in paths:
    try:
        response = urllib.request.urlopen(urllib.request.Request(base + p, method='GET'), timeout=30)
        status, body = response.status, response.read().decode('utf-8', 'replace')
    except urllib.error.HTTPError as error:
        status, body = error.code, ''
    meta = re.search(r'<meta name="robots" content="([^"]+)"', body)
    sample = 'Sample content.' in body
    report['checked'].append({'path': p, 'status': status, 'meta_robots': meta.group(1) if meta else None, 'sample_notice': sample})
    if status != 200:
        problem(f'{p} answered {status}')
    if sample:
        problem(f'{p} carries the sample notice')
    if meta and 'noindex' in meta.group(1):
        problem(f'{p} is noindex in the page itself')

# The database's view: published pages (sample and not), published articles and which carry the sample topic.
pages = rows("SELECT slug, IFNULL(template,'standard') FROM pages WHERE status='published'")
sample_topic = rows("SELECT id FROM post_topics WHERE slug='sample-content'")
sample_id = int(sample_topic[0][0]) if sample_topic else None
posts = rows("SELECT slug, IFNULL(topic_ids,'[]') FROM posts WHERE status='published'")
listed = set(paths)
sample_pages = sorted('/' + slug for slug, template in pages if template == 'sample')
real_pages = sorted('/' + slug for slug, template in pages if template != 'sample')
sample_posts = sorted('/insights/' + slug for slug, topics in posts if sample_id is not None and sample_id in json.loads(topics))
real_posts = sorted('/insights/' + slug for slug, topics in posts if not (sample_id is not None and sample_id in json.loads(topics)))
report['database_view'] = {'published_pages': len(pages), 'sample_pages': len(sample_pages), 'published_articles': len(posts), 'sample_articles': len(sample_posts), 'sample_topic_id': sample_id}
report['sample_pages_listed'] = sorted(set(sample_pages) & listed)
report['sample_articles_listed'] = sorted(set(sample_posts) & listed)
report['real_pages_missing'] = sorted(set(real_pages) - listed)
report['real_articles_missing'] = sorted(set(real_posts) - listed)
report['solutions_listed'] = sorted(p for p in listed if p.startswith(('/workspace-solutions', '/business-solutions')))
report['sample_topic_listed'] = '/insights/topic/sample-content' in listed
for key in ['sample_pages_listed', 'sample_articles_listed', 'real_pages_missing', 'real_articles_missing', 'solutions_listed']:
    if report[key]:
        problem(f'{key}: {report[key]}')
if report['sample_topic_listed']:
    problem('sample topic listed')
report['paths'] = paths
print(json.dumps(report, indent=1))
