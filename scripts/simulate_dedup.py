import re
import sys
from collections import defaultdict

SQL_FILE = r"c:\xampp\htdocs\dev\uf_gestion\a0011086_erp_mvp_fulldb.sql"

def extract_insert_blocks(sql):
    pattern = re.compile(r"INSERT INTO `(?P<table>[^`]+)`\s*\((?P<cols>[^)]+)\)\s*VALUES\s*(?P<vals>.*?);", re.DOTALL)
    return pattern.finditer(sql)


def parse_tuples(values_text):
    tuples = []
    i = 0
    n = len(values_text)
    while i < n:
        # find next '('
        while i < n and values_text[i] != '(':
            i += 1
        if i >= n: break
        i += 1
        start = i
        depth = 1
        in_quote = False
        quote_char = ''
        while i < n and depth > 0:
            ch = values_text[i]
            if in_quote:
                if ch == quote_char:
                    # check for escaped quote by backslash
                    if i+1 < n and values_text[i+1] == quote_char:
                        # SQL may use doubled quotes; skip
                        i += 1
                    else:
                        in_quote = False
                elif ch == '\\':
                    i += 1 # skip escape next
            else:
                if ch == '"' or ch == "'":
                    in_quote = True
                    quote_char = ch
                elif ch == '(':
                    depth += 1
                elif ch == ')':
                    depth -= 1
            i += 1
        end = i-1
        tuple_text = values_text[start:end].strip()
        tuples.append(tuple_text)
    return tuples


def split_top_commas(s):
    parts = []
    cur = []
    in_quote = False
    quote_char = ''
    i = 0
    while i < len(s):
        ch = s[i]
        if in_quote:
            cur.append(ch)
            if ch == quote_char:
                # handle doubled quote
                if i+1 < len(s) and s[i+1] == quote_char:
                    cur.append(s[i+1]); i += 1
                else:
                    in_quote = False
        else:
            if ch == '"' or ch == "'":
                in_quote = True
                quote_char = ch
                cur.append(ch)
            elif ch == ',':
                parts.append(''.join(cur).strip())
                cur = []
            else:
                cur.append(ch)
        i += 1
    if cur:
        parts.append(''.join(cur).strip())
    return parts


def normalize_value(v):
    v = v.strip()
    if v.upper() == 'NULL':
        return '<NULL>'
    # remove surrounding quotes
    if (v.startswith("'") and v.endswith("'")) or (v.startswith('"') and v.endswith('"')):
        v = v[1:-1]
        v = v.replace("\\r","\\\r").replace("\\n","\\\n")
        v = v.replace("''","'")
    return v


def main():
    try:
        with open(SQL_FILE, 'r', encoding='utf-8', errors='ignore') as f:
            sql = f.read()
    except Exception as e:
        print('ERROR: no pude leer el archivo:', e)
        sys.exit(1)

    results = {}
    total_possible_deletes = 0

    for m in extract_insert_blocks(sql):
        table = m.group('table')
        cols = [c.strip().strip('`') for c in m.group('cols').split(',')]
        vals = m.group('vals')
        tuples = parse_tuples(vals)
        keymap = defaultdict(list)
        for t in tuples:
            cols_vals = split_top_commas(t)
            if len(cols_vals) != len(cols):
                # try to be resilient: skip malformed
                continue
            # find index of id column
            try:
                id_idx = cols.index('id')
            except ValueError:
                # no id column: skip
                id_idx = None
            if id_idx is None:
                # Create a key from all columns
                key_parts = [normalize_value(x) for x in cols_vals]
                key = '||'.join(key_parts)
                # store synthetic id as None
                keymap[key].append(None)
            else:
                id_val = normalize_value(cols_vals[id_idx])
                # build key using all columns except id
                key_parts = [normalize_value(cols_vals[i]) for i in range(len(cols)) if i != id_idx]
                key = '||'.join(key_parts)
                keymap[key].append(id_val)
        # compute duplicates
        deletes = sum(len(v)-1 for v in keymap.values() if len(v) > 1)
        results[table] = {'rows': sum(len(v) for v in keymap.values()), 'duplicate_groups': sum(1 for v in keymap.values() if len(v)>1), 'deletes': deletes}
        total_possible_deletes += deletes

    # print results
    print('Simulación de deduplicado (conservar id menor).')
    print('Archivo:', SQL_FILE)
    print()
    print('{:<40} {:>10} {:>15} {:>10}'.format('Tabla','Filas','Grupos dup','A eliminar'))
    print('-'*80)
    for t,info in sorted(results.items(), key=lambda x: x[0]):
        print('{:<40} {:>10} {:>15} {:>10}'.format(t, info['rows'], info['duplicate_groups'], info['deletes']))
    print('-'*80)
    print('Total filas a eliminar estimadas:', total_possible_deletes)

if __name__ == '__main__':
    main()
