# Writes a tiny one-page PDF used as the demo learning material.
lines = [("F2",22,"Modul 3: Subnetting IPv4"),("F1",13,"Administrasi Sistem Jaringan - Kelas X TKJ 1"),("F1",12,""),
 ("F2",15,"A. Tujuan Pembelajaran"),("F1",12,"Siswa mampu menghitung subnet, host, dan broadcast address."),("F1",12,""),
 ("F2",15,"B. Rumus Dasar"),("F1",12,"Jumlah subnet = 2^n    Jumlah host = 2^h - 2"),("F1",12,""),
 ("F2",15,"C. Contoh: 192.168.10.0/26"),("F1",12,"Subnet mask   : 255.255.255.192"),("F1",12,"Host per subnet : 62"),
 ("F1",12,"Network       : 192.168.10.0"),("F1",12,"Broadcast     : 192.168.10.63"),("F1",12,""),
 ("F2",15,"D. Latihan"),("F1",12,"1. Hitung jumlah host pada prefix /27."),("F1",12,"2. Tentukan broadcast dari 10.0.0.0/28.")]
y=770; ops=["0.31 0.27 0.9 rg 0 800 595 42 re f 0 0 0 rg"]
for f,s,t in lines:
    t=t.replace("\\","\\\\").replace("(","\(").replace(")","\)")
    ops.append(f"BT /{f} {s} Tf 60 {y} Td ({t}) Tj ET"); y-= s+12
stream="\n".join(ops).encode()
objs=[b"<< /Type /Catalog /Pages 2 0 R >>",b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
 b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>",
 b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>",
 b"<< /Length %d >>\nstream\n"%len(stream)+stream+b"\nendstream"]
out=b"%PDF-1.4\n"; offs=[]
for i,o in enumerate(objs): offs.append(len(out)); out+=b"%d 0 obj\n"%(i+1)+o+b"\nendobj\n"
x=len(out); out+=b"xref\n0 %d\n0000000000 65535 f \n"%(len(objs)+1)+b"".join(b"%010d 00000 n \n"%o for o in offs)
out+=b"trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n"%(len(objs)+1,x)
open("modul-demo.pdf","wb").write(out)
