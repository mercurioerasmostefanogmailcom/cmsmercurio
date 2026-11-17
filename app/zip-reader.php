<?php
/** Lettore ZIP minimale di fallback per server senza ext-zip. Supporta ZIP classici, STORE e DEFLATE; rifiuta ZIP64 e archivi cifrati. */
final class CmsZipReader {
    public int $numFiles = 0;
    private string $file = '';
    private string $bytes = '';
    private array $entries = [];
    public function open(string $file) {
        $data = @file_get_contents($file);
        if (!is_string($data) || strlen($data) < 22) return false;
        $this->file = $file; $this->bytes = $data; $this->entries = [];
        $start = max(0, strlen($data) - 65557);
        $eocd = strrpos(substr($data, $start), "PK\x05\x06");
        if ($eocd === false) return false;
        $eocd += $start;
        if (strlen($data) < $eocd + 22) return false;
        $h = unpack('vdisk/vcdisk/ventries_disk/ventries/Vcd_size/Vcd_offset/vcomment', substr($data, $eocd + 4, 18));
        if (!$h || $h['disk'] !== 0 || $h['cdisk'] !== 0 || $h['entries'] !== $h['entries_disk'] || $h['entries'] > 1000) return false;
        if ($h['cd_offset'] > strlen($data) || $h['cd_size'] > strlen($data) - $h['cd_offset']) return false;
        $pos = (int)$h['cd_offset'];
        for ($i=0; $i<(int)$h['entries']; $i++) {
            if (substr($data,$pos,4) !== "PK\x01\x02" || $pos+46 > strlen($data)) return false;
            $v = unpack('vmade/vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen/vclen/vdisk/vint/Vext/Voffset', substr($data,$pos+4,42));
            if (!$v) return false;
            $nameLen=(int)$v['nlen']; $extraLen=(int)$v['elen']; $commentLen=(int)$v['clen'];
            $name=substr($data,$pos+46,$nameLen);
            if (strlen($name)!==$nameLen || $pos+46+$nameLen+$extraLen+$commentLen>strlen($data)) return false;
            if (($v['flag'] & 1) || !in_array((int)$v['method'],[0,8],true) || $v['csize']===0xffffffff || $v['usize']===0xffffffff || $v['offset']===0xffffffff) return false;
            $this->entries[]=['name'=>$name,'flag'=>(int)$v['flag'],'method'=>(int)$v['method'],'crc'=>(int)$v['crc'],'csize'=>(int)$v['csize'],'usize'=>(int)$v['usize'],'offset'=>(int)$v['offset'],'ext'=>(int)$v['ext'],'made'=>(int)$v['made']];
            $pos += 46+$nameLen+$extraLen+$commentLen;
        }
        $this->numFiles=count($this->entries);
        return true;
    }
    public function statIndex(int $i) { if (!isset($this->entries[$i])) return false; $e=$this->entries[$i]; return ['name'=>$e['name'],'size'=>$e['usize']]; }
    public function statName(string $name) { foreach($this->entries as $e) if($e['name']===$name) return ['name'=>$name,'size'=>$e['usize']]; return false; }
    public function getFromName(string $name) { foreach($this->entries as $i=>$e) if($e['name']===$name) return $this->getFromIndex($i); return false; }
    public function getFromIndex(int $i) {
        if (!isset($this->entries[$i])) return false; $e=$this->entries[$i]; $p=$e['offset']; $d=$this->bytes;
        if (substr($d,$p,4)!=="PK\x03\x04" || $p+30>strlen($d)) return false;
        $h=unpack('vneed/vflag/vmethod/vtime/vdate/Vcrc/Vcsize/Vusize/vnlen/velen',substr($d,$p+4,26));
        if (!$h || ($h['flag']&1) || $h['method']!==$e['method']) return false;
        $dataPos=$p+30+(int)$h['nlen']+(int)$h['elen'];
        if ($dataPos<0 || $e['csize']>strlen($d)-$dataPos) return false;
        $compressed=substr($d,$dataPos,$e['csize']);
        if ($e['method']===0) $out=$compressed;
        else { $out=@gzinflate($compressed, max(1,$e['usize'])); if (!is_string($out)) return false; }
        if (strlen($out)!==$e['usize'] || sprintf('%u',crc32($out))!==sprintf('%u',$e['crc'])) return false;
        return $out;
    }
    public function getExternalAttributesIndex(int $i, &$opsys, &$attr): bool {
        if (!isset($this->entries[$i])) return false; $e=$this->entries[$i]; $opsys=($e['made']>>8)&255; $attr=$e['ext']; return true;
    }
    public function close(): bool { $this->bytes=''; $this->entries=[]; $this->numFiles=0; return true; }
}
