# Logo assets v2. Source emblem: input/logo-emblem-full.png (full ring, from Moayed, 22 Nov 2026).
# Wordmark: the company name from the original logo, set as text in Expo Arabic (brand font) -- same words, same colours.
# White background -> transparent by colour-to-alpha; design and colours unchanged.
from PIL import Image, ImageDraw, ImageFont; import numpy as np
ROOT='/workspace/ard-alhayat'; SRC=ROOT+'/input/logo-emblem-full.png'; IMG=ROOT+'/site/public/assets/img/'
BOLD=ROOT+'/input/fonts/f1/Expo Arabic Bold/Expo Arabic Bold.ttf'; BOOK=ROOT+'/input/fonts/f2/ArbFonts.com-(1)/ArbFONTS-ExpoArabic-Book.ttf'
INK=(28,43,10); CREAM=(246,238,218)

def to_alpha(path):
    a=np.asarray(Image.open(path).convert('RGB')).astype(float)/255
    al=np.clip(((1-a.min(axis=2))-0.10)/0.90,0,1); s=np.maximum(al,1e-6)[...,None]
    rgb=np.clip((a-(1-s))/s,0,1); return np.dstack([rgb,al])
def trim(arr,pad_frac):
    ys,xs=np.where(arr[...,3]>0.12); arr=arr[ys.min():ys.max()+1,xs.min():xs.max()+1]
    h,w=arr.shape[:2]; side=max(h,w); p=round(side*pad_frac)
    out=np.zeros((side+2*p,side+2*p,4)); oy=p+(side-h)//2; ox=p+(side-w)//2; out[oy:oy+h,ox:ox+w]=arr; return out
def light(arr):
    rgb=arr[...,:3]; v=rgb.max(axis=2)
    t=np.clip((0.30-v)/0.08,0,1)[...,None]          # dark green ink -> cream; gradient (brighter) stays
    grad=np.clip(rgb*1.25+0.04,0,1)                   # lift the gradient a little for dark backgrounds
    out=arr.copy(); out[...,:3]=t*(np.array(CREAM)/255)+(1-t)*grad; return out
def img(arr): return Image.fromarray((arr*255).round().astype('uint8'),'RGBA')
def save(im,name,w=None):
    if w: im=im.resize((w,round(im.height*w/im.width)),Image.LANCZOS)
    if name.endswith('.webp'): im.save(IMG+name,quality=88,method=6,alpha_quality=92)
    else: im.save(IMG+name,optimize=True)
    return im

mark=trim(to_alpha(SRC),0.04); dark=img(mark); lite=img(light(mark))
save(dark,'brand-mark.webp',240); save(lite,'brand-mark-light.webp',240)

def lockup(m,ink,light_mode):
    W=1200; mw=620; m=m.resize((mw,mw),Image.LANCZOS)
    f1=ImageFont.truetype(BOLD,150,layout_engine=ImageFont.Layout.RAQM); f2=ImageFont.truetype(BOOK,52,layout_engine=ImageFont.Layout.RAQM)
    name_a,name_b='شركة أرض','الحياة'; sub='للتنمية الزراعية والإنتاج الحيواني المساهمة'
    tmp=ImageDraw.Draw(Image.new('L',(1,1)))
    bb=lambda t,f: tmp.textbbox((0,0),t,font=f,direction='rtl',language='ar')
    ba,bb2,bs=bb(name_a,f1),bb(name_b,f1),bb(sub,f2); gap=40
    nw=(ba[2]-ba[0])+gap+(bb2[2]-bb2[0]); nh=max(ba[3],bb2[3])-min(ba[1],bb2[1])
    H=mw+40+nh+30+(bs[3]-bs[1])
    c=Image.new('RGBA',(W,H),(0,0,0,0)); c.alpha_composite(m,((W-mw)//2,0)); d=ImageDraw.Draw(c)
    y=mw+40-min(ba[1],bb2[1]); x0=(W-nw)//2
    # RTL: "الحياة" sits on the left in gold gradient (as in the original logo), "شركة أرض" on the right in ink
    gold=Image.new('RGBA',(bb2[2]-bb2[0]+20,nh+60),(0,0,0,0)); gm=Image.new('L',gold.size,0)
    ImageDraw.Draw(gm).text((-bb2[0]+10,-min(ba[1],bb2[1])+10),name_b,font=f1,fill=255,direction='rtl',language='ar')
    g=np.zeros((gold.size[1],gold.size[0],4),dtype=np.uint8); ys=np.linspace(0,1,gold.size[1])[:,None]
    top=np.array([226,174,76]) if light_mode else np.array([200,140,30]); bot=np.array([150,92,22]) if not light_mode else np.array([200,140,40])
    g[...,:3]=(top*(1-ys[...,None])+bot*ys[...,None]).astype(np.uint8).repeat(gold.size[0],1); g[...,3]=np.asarray(gm)
    c.alpha_composite(Image.fromarray(g,'RGBA'),(x0-10,mw+40-10))
    d.text((x0+(bb2[2]-bb2[0])+gap-ba[0],y+min(ba[1],bb2[1])-ba[1]+ba[1]),name_a,font=f1,fill=ink+(255,),direction='rtl',language='ar')
    d.text(((W-(bs[2]-bs[0]))//2-bs[0],mw+40+nh+30-bs[1]),sub,font=f2,fill=ink+(255,) if not light_mode else (236,228,206,255),direction='rtl',language='ar')
    bbx=c.getbbox(); c=c.crop(bbx); p=40; o=Image.new('RGBA',(c.width+2*p,c.height+2*p),(0,0,0,0)); o.alpha_composite(c,(p,p)); return o
ld=lockup(dark,INK,False); ll=lockup(lite,CREAM,True)
save(ld,'brand-lockup.webp',480); save(ld,'logo.png',600)

# icons: mark on the site's paper colour, rounded square
def icon(size,radius_frac=0.22,scale=0.84):
    bg=Image.new('RGBA',(size,size),(0,0,0,0)); mk=Image.new('L',(size,size),0)
    ImageDraw.Draw(mk).rounded_rectangle((0,0,size-1,size-1),radius=round(size*radius_frac),fill=255)
    bg.paste(Image.new('RGBA',(size,size),(247,243,232,255)),(0,0),mk)
    s=round(size*scale); m=dark.resize((s,s),Image.LANCZOS); bg.alpha_composite(m,((size-s)//2,(size-s)//2)); return bg
save(icon(512),'icon-v2-512.png'); save(icon(192),'icon-v2-192.png'); save(icon(180,0.0,0.86),'apple-touch-icon-v2.png')
fav=dark.resize((64,64),Image.LANCZOS); save(dark.resize((32,32),Image.LANCZOS),'favicon-v2-32.png')
fav.save(ROOT+'/site/public/favicon.ico',sizes=[(16,16),(32,32),(48,48),(64,64)])
print('ok', dark.size, ld.size)

# Footer keeps the previous lockup (original logo.jpg wordmark, light on dark) at Moayed's request (22 Nov 2026):
# site/public/assets/img/footer-logo-light.webp is the v1 file (sha1 9fcb03ee...), built from input/logo.jpg by the v1 colour-to-alpha + light() steps. Not regenerated here.
