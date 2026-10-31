import json,sys
a=json.load(open(sys.argv[1])); b=json.load(open(sys.argv[2])); ta,tb=a['tables'],b['tables']
out={'new_tables':sorted(set(tb)-set(ta)),'dropped_tables':sorted(set(ta)-set(tb)),'changed':{}}
for t in sorted(set(ta)&set(tb)):
    if (ta[t]['rows'],ta[t]['hash'],ta[t]['columns'])!=(tb[t]['rows'],tb[t]['hash'],tb[t]['columns']):
        out['changed'][t]={'rows_before':ta[t]['rows'],'rows_after':tb[t]['rows'],'schema_changed':ta[t]['columns']!=tb[t]['columns']}
print(json.dumps(out))
