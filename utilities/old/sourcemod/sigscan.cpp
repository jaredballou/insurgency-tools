#include <stdio.h>
 
#ifdef WIN32
    #define WIN32_LEAN_AND_MEAN
    #include <windows.h>
#else
    #include <dlfcn.h>
    #include <sys/types.h>
    #include <sys/stat.h> 
#endif
 
#include "sigscan.h"
 
/* There is no ANSI ustrncpy */
unsigned char* ustrncpy(unsigned char *dest, const unsigned char *src, int len) {
    while(len--)
        dest[len] = src[len];
 
    return dest;
}
 
/* //////////////////////////////////////
    CSigScan Class
    ////////////////////////////////////// */
unsigned char* CSigScan::base_addr;
size_t CSigScan::base_len;
void *(*CSigScan::sigscan_dllfunc)(const char *pName, int *pReturnCode);
 
/* Initialize the Signature Object */
void CSigScan::Init(unsigned char *sig, char *mask, size_t len) {
    is_set = 0;
 
    sig_len = len;
    sig_str = new unsigned char[sig_len];
    ustrncpy(sig_str, sig, sig_len);
 
    sig_mask = new char[sig_len+1];
    strncpy(sig_mask, mask, sig_len);
    sig_mask[sig_len+1] = 0;
 
    if(!base_addr)
        return ; // GetDllMemInfo() Failed
 
    if((sig_addr = FindSignature()) == NULL)
        return ; // FindSignature() Failed
 
    is_set = 1;
    // SigScan Successful!
}
 
/* Destructor frees sig-string allocated memory */
CSigScan::~CSigScan(void) {
    delete[] sig_str;
    delete[] sig_mask;
}
 
/* Get base address of the server module (base_addr) and get its ending offset (base_len) */
bool CSigScan::GetDllMemInfo(void) {
    void *pAddr = (void*)sigscan_dllfunc;
    base_addr = 0;
    base_len = 0;
 
    #ifdef WIN32
    MEMORY_BASIC_INFORMATION mem;
 
    if(!pAddr)
        return false; // GetDllMemInfo failed!pAddr
 
    if(!VirtualQuery(pAddr, &mem, sizeof(mem)))
        return false;
 
    base_addr = (unsigned char*)mem.AllocationBase;
 
    IMAGE_DOS_HEADER *dos = (IMAGE_DOS_HEADER*)mem.AllocationBase;
    IMAGE_NT_HEADERS *pe = (IMAGE_NT_HEADERS*)((unsigned long)dos+(unsigned long)dos->e_lfanew);
 
    if(pe->Signature != IMAGE_NT_SIGNATURE) {
        base_addr = 0;
        return false; // GetDllMemInfo failedpe points to a bad location
    }
 
    base_len = (size_t)pe->OptionalHeader.SizeOfImage;
 
    #else
 
    Dl_info info;
    struct stat buf;
 
    if(!dladdr(pAddr, &info))
        return false;
 
    if(!info.dli_fbase || !info.dli_fname)
        return false;
 
    if(stat(info.dli_fname, &buf) != 0)
        return false;
 
    base_addr = (unsigned char*)info.dli_fbase;
    base_len = buf.st_size;
    #endif
 
    return true;
}
 
/* Scan for the signature in memory then return the starting position's address */
void* CSigScan::FindSignature(void) {
    unsigned char *pBasePtr = base_addr;
    unsigned char *pEndPtr = base_addr+base_len;
    size_t i;
 
    while(pBasePtr < pEndPtr) {
        for(i = 0;i < sig_len;i++) {
            if((sig_mask[i] != '?') && (sig_str[i] != pBasePtr[i]))
                break;
        }
 
        // If 'i' reached the end, we know we have a match!
        if(i == sig_len)
            return (void*)pBasePtr;
 
        pBasePtr++;
    }
 
    return NULL;
}
 
/* Signature Objects */
CSigScan CBaseAnimating_Ignite_Sig;
 
/* Set the static base_addr and base_len variables then initialize all Signature Objects */
void InitSigs(void) {
    CSigScan::GetDllMemInfo();
 
    /* void CBaseAnimating::Ignite(float flFlameLifetime, bool bNPCOnly, float flSize,
        bool bCalledByLevelDesigner);
    Last Address: 0x220BC7A0
    Signature: 56  8B  F1  8B? 86? BC? 00? 00? 00? C1? E8? 1B? A8? 01? 0F? 85?
           9A? 00? 00? 00? 8B  16  FF  92? F0? 00? 00? 00? 80? 7C? 24? 0C?
           00? 74? 08? 84  C0  0F? 84? 83? 00? 00? 00? 3C  01  75? 20? 80
           7C  24  14  00  75? 19? 8B  CE  E8  83? 1A? 01? 00? 85? C0? 74?
           0E? 8B  10  8B  C8  FF  92? 08? 05? 00? 00? 84  C0  74? 5F? 57
           6A  01  56  E8  48? EA? 07? 00? 8B  F8  83  C4  08  85  FF  74?
           3D? 8B  44  24  0C  50  8B  CF  E8  83? E5? 07? 00? 68  00  00
           00  08  8B  CE
    */
    CBaseAnimating_Ignite_Sig.Init((unsigned char*)
    "\x56\x8B\xF1\x8B\x86\xBC\x00\x00\x00\xC1\xE8\x1B\xA8\x01\x0F\x85\x9A\x00\x00\x00"
    "\x8B\x16\xFF\x92\xF0\x00\x00\x00\x80\x7C\x24\x0C\x00\x74\x08\x84\xC0\x0F\x84\x83"
    "\x00\x00\x00\x3C\x01\x75\x20\x80\x7C\x24\x14\x00\x75\x19\x8B\xCE\xE8\x83\x1A\x01"
    "\x00\x85\xC0\x74\x0E\x8B\x10\x8B\xC8\xFF\x92\x08\x05\x00\x00\x84\xC0\x74\x5F\x57"
    "\x6A\x01\x56\xE8\x48\xEA\x07\x00\x8B\xF8\x83\xC4\x08\x85\xFF\x74\x3D\x8B\x44\x24"
    "\x0C\x50\x8B\xCF\xE8\x83\xE5\x07\x00\x68\x00\x00\x00\x08\x8B\xCE"
    ,
    "xxx?????????????????"
    "xxx????????????xx???"
    "???xx??xxxxx??xxx???"
    "?????xxxxx?????xx??x"
    "xxxx????xxxxxxx??xxx"
    "xxxxx????xxxxxxx"
    ,
    116);
 
    return ;
}
 
/* Example of a sig-scanned method function */
void CBaseAnimating_Ignite(CBaseAnimating *cba, float flFlameLifetime) {
    int bNPCOnly = false, bCalledByLevelDesigner = false;
    float flSize = 0.0f;
 
    if(!CBaseAnimating_Ignite_Sig.is_set)
        return ; // sigscan failed
 
    union {
        void (EmptyClass::*mfpnew)(float, bool, float, bool);
        void* addr;
    } u;
    u.addr = CBaseAnimating_Ignite_Sig.sig_addr;
 
    (reinterpret_cast<EmptyClass*>(cba)->*u.mfpnew)(flFlameLifetime, (bool)bNPCOnly, flSize,
        (bool)bCalledByLevelDesigner);
 
    return;
}
