import json,sys
a=json.load(open(sys.argv[1]))['tables']; b=json.load(open(sys.argv[2]))['tables']
print('tables before', len(a), 'after', len(b))
new=sorted(set(b)-set(a)); gone=sorted(set(a)-set(b))
print('removed tables:', gone)
print('new tables (%d):' % len(new), {t: b[t]['rows'] for t in new})
changed=[t for t in a if t in b and (a[t]['rows'],a[t]['hash'])!=(b[t]['rows'],b[t]['hash'])]
print('existing tables whose existing data (rows or values of earlier columns) changed:', {t:(a[t]['rows'],b[t]['rows']) for t in changed})
for t in a:
    if t not in b: continue
    ca={c['name']:c for c in a[t]['columns']}; cb={c['name']:c for c in b[t]['columns']}
    added=[c for c in cb if c not in ca]; removed=[c for c in ca if c not in cb]; altered=[c for c in ca if c in cb and ca[c]!=cb[c]]
    if added or removed or altered: print('  column changes', t, 'added', [(c, cb[c]['type'], cb[c]['nullable'], cb[c]['default']) for c in added], 'removed', removed, 'altered', altered)
    if a[t]['indexes']!=b[t]['indexes'] or a[t]['foreign_keys']!=b[t]['foreign_keys']: print('  index/FK changes', t, sorted(set(b[t]['indexes'])-set(a[t]['indexes'])), sorted(set(b[t]['foreign_keys'])-set(a[t]['foreign_keys'])))
