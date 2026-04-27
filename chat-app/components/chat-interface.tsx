"use client"

import { useState, useEffect, useRef } from "react"
import { Send, Bot, Loader2, Copy, RefreshCw, Check, Paperclip, X, FileText } from "lucide-react"
import { SidebarProvider, SidebarInset, SidebarTrigger } from "@/components/ui/sidebar"
import { Button } from "@/components/ui/button"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Avatar, AvatarFallback } from "@/components/ui/avatar"
import { AppSidebar } from "@/components/app-sidebar"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import ReactMarkdown from 'react-markdown'
import remarkGfm from 'remark-gfm'
import { Prism as SyntaxHighlighter } from 'react-syntax-highlighter'
import { vscDarkPlus } from 'react-syntax-highlighter/dist/esm/styles/prism'
import { apiFetch, getUser, clearToken } from "@/lib/auth"
import { useRouter } from "next/navigation"
import Link from "next/link"
import { Switch } from "@/components/ui/switch"

const API_BASE = process.env.NEXT_PUBLIC_API_BASE ?? "http://localhost:8000/api"
const STORAGE_BASE = process.env.NEXT_PUBLIC_STORAGE_BASE ?? "http://localhost:8000/storage"

const PROVIDER_LABELS: Record<string, string> = {
  openai: 'GPT-3.5',
  claude: 'Claude',
  gemini: 'Gemini',
  ollama: 'Ollama',
}

const PROVIDER_COLORS: Record<string, string> = {
  openai: 'text-emerald-500',
  claude: 'text-orange-400',
  gemini: 'text-blue-400',
  ollama: 'text-purple-400',
}

// --- CopyButton ---
function CopyButton({ text, label = "Copy" }: { text: string, label?: string }) {
  const [copied, setCopied] = useState(false)
  return (
    <button
      onClick={() => { navigator.clipboard.writeText(text); setCopied(true); setTimeout(() => setCopied(false), 2000) }}
      className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors"
    >
      {copied ? <Check className="w-3 h-3" /> : <Copy className="w-3 h-3" />}
      {copied ? "Copied!" : label}
    </button>
  )
}

// --- ChatInput (isolated to prevent re-renders) ---
function ChatInput({ isTyping, onSend }: { isTyping: boolean, onSend: (val: string, images: File[], pdfs: File[]) => void }) {
  const [input, setInput] = useState("")
  const [images, setImages] = useState<File[]>([])
  const [pdfs, setPdfs] = useState<File[]>([])
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const pdfInputRef = useRef<HTMLInputElement>(null)

  const canSend = (input.trim() || images.length > 0 || pdfs.length > 0) && !isTyping

  const doSend = () => {
    if (!canSend) return
    onSend(input, images, pdfs)
    setInput("")
    setImages([])
    setPdfs([])
    if (textareaRef.current) textareaRef.current.style.height = "auto"
  }

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey) { e.preventDefault(); doSend() }
  }

  const hasPreviews = images.length > 0 || pdfs.length > 0

  return (
    <div className="p-4 bg-background pb-8 pt-0">
      <div className="max-w-3xl mx-auto backdrop-blur-md bg-muted/40 relative rounded-3xl border shadow-sm flex flex-col focus-within:bg-muted/80 transition-colors">
        {/* File previews */}
        {hasPreviews && (
          <div className="flex flex-wrap gap-2 px-4 pt-4 pb-1">
            {images.map((file, idx) => (
              <div key={'img-' + idx} className="relative group">
                <img src={URL.createObjectURL(file)} alt="preview" className="w-16 h-16 object-cover rounded-xl border opacity-90 group-hover:opacity-100 transition-opacity" />
                <button onClick={() => setImages(p => p.filter((_, i) => i !== idx))} className="absolute -top-2 -right-2 bg-destructive text-destructive-foreground rounded-full p-0.5 opacity-0 group-hover:opacity-100 transition-opacity shadow-sm">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
            {pdfs.map((file, idx) => (
              <div key={'pdf-' + idx} className="relative group flex items-center gap-1.5 bg-muted border rounded-xl px-3 py-2 text-xs font-medium">
                <FileText className="w-4 h-4 text-red-400 shrink-0" />
                <span className="max-w-[100px] truncate">{file.name}</span>
                <button onClick={() => setPdfs(p => p.filter((_, i) => i !== idx))} className="ml-1 text-muted-foreground hover:text-destructive transition-colors">
                  <X className="w-3 h-3" />
                </button>
              </div>
            ))}
          </div>
        )}

        <div className="flex items-end pr-2 pb-2 pl-2">
          {/* Attachment buttons */}
          <div className="pb-[18px] flex items-center gap-0.5 shrink-0">
            <button onClick={() => fileInputRef.current?.click()} title="Attach image" className="text-muted-foreground hover:text-foreground transition-colors p-2 rounded-full hover:bg-muted/50">
              <Paperclip className="w-5 h-5" />
            </button>
            <button onClick={() => pdfInputRef.current?.click()} title="Attach PDF" className="text-muted-foreground hover:text-foreground transition-colors p-2 rounded-full hover:bg-muted/50">
              <FileText className="w-5 h-5" />
            </button>
            <input type="file" ref={fileInputRef} className="hidden" multiple accept="image/*" onChange={(e) => { if (e.target.files) setImages(p => [...p, ...Array.from(e.target.files as FileList)]); e.target.value = '' }} />
            <input type="file" ref={pdfInputRef} className="hidden" multiple accept=".pdf" onChange={(e) => { if (e.target.files) setPdfs(p => [...p, ...Array.from(e.target.files as FileList)]); e.target.value = '' }} />
          </div>

          <textarea
            ref={textareaRef}
            value={input}
            onChange={e => setInput(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Ask Nexus AI..."
            className="w-full max-h-64 min-h-[56px] resize-none bg-transparent border-none px-4 py-[18px] text-[15px] focus:outline-none focus:ring-0 leading-tight"
            disabled={isTyping}
            rows={1}
            style={{ height: "auto" }}
            onInput={(e) => { const t = e.target as HTMLTextAreaElement; t.style.height = 'auto'; t.style.height = t.scrollHeight + 'px' }}
          />

          <div className="pb-2 shrink-0">
            <Button disabled={!canSend} onClick={doSend} size="icon" className="rounded-full w-9 h-9 shadow-sm">
              <Send className="h-4 w-4 ml-0.5" />
            </Button>
          </div>
        </div>
      </div>
      <p className="text-center mt-2 text-xs text-muted-foreground opacity-60">
        Enter to send · Shift+Enter for newline · Attach images or PDFs for context
      </p>
    </div>
  )
}

// --- Main Page ---
export default function ChatInterface({ initialChatId = null }: { initialChatId?: string | null }) {
  const [chats, setChats] = useState<any[]>([])
  const [activeChatId, setActiveChatId] = useState<string | null>(initialChatId)
  const [messages, setMessages] = useState<any[]>([])
  const [isTyping, setIsTyping] = useState(false)
  const [streamingMsgId, setStreamingMsgId] = useState<number | null>(null)
  const [provider, setProvider] = useState("ollama")
  const router = useRouter()
  const user = getUser()
  const [isTemporary, setIsTemporary] = useState(user ? false : true)
  const scrollRef = useRef<HTMLDivElement>(null)

  const fetchChats = async () => {
    if (!user) return
    try {
      const res = await apiFetch(`${API_BASE}/chats`)
      if (res.ok) setChats(await res.json())
    } catch (e) { console.error(e) }
  }

  useEffect(() => { fetchChats() }, [])
  useEffect(() => { scrollRef.current?.scrollIntoView({ behavior: "smooth" }) }, [messages, isTyping])

  // Load chat when initialChatId changes (from URL navigation)
  useEffect(() => {
    if (initialChatId) {
      loadChatFromApi(initialChatId)
    } else {
      setActiveChatId(null)
      setMessages([])
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [initialChatId])

  const createChat = () => {
    setActiveChatId(null)
    setMessages([])
    setIsTemporary(false)
    // Update URL without triggering a page navigation
    window.history.replaceState(null, '', '/')
  }

  // Load chat data from API and set state (used by both sidebar clicks and URL navigation)
  const loadChatFromApi = async (id: string) => {
    try {
      setActiveChatId(id)
      setMessages([]) // Clear messages immediately to show loading state
      const res = await apiFetch(`${API_BASE}/chats/${id}`)
      if (res.ok) {
        const data = await res.json()
        setMessages(data.messages || [])
      }
    } catch (e) { console.error(e) }
  }

  // Handle sidebar chat click — load in-place without page navigation
  const handleSidebarChatClick = (id: string) => {
    if (id === activeChatId) return // Already viewing this chat
    setIsTemporary(false)
    loadChatFromApi(id)
    // Update URL without causing a full page navigation/re-mount
    window.history.pushState(null, '', `/chat/${id}`)
  }

  const deleteChat = async (id: string) => {
    const res = await apiFetch(`${API_BASE}/chats/${id}`, { method: "DELETE" })
    if (!res.ok) throw new Error("Delete failed")
    setChats(prev => prev.filter((c: any) => c.id !== id))
    if (activeChatId === id) {
      setActiveChatId(null)
      setMessages([])
      window.history.replaceState(null, '', '/')
    }
  }

  const handleLogout = async () => {
    try {
      await apiFetch(`${API_BASE}/auth/logout`, { method: "POST" })
    } catch (e) { console.error(e) }
    clearToken()
    router.push('/login')
  }

  const handleSend = async (contentToSend: string, imagesToUpload: File[] = [], pdfsToUpload: File[] = []) => {
    if ((!contentToSend.trim() && imagesToUpload.length === 0 && pdfsToUpload.length === 0) || isTyping) return

    let currentChatId = activeChatId
    if (!currentChatId && !isTemporary) {
      try {
        const res = await apiFetch(`${API_BASE}/chats`, {
          method: "POST",
          body: JSON.stringify({ title: contentToSend.substring(0, 40) || 'File Session' })
        })
        if (res.ok) {
          const data = await res.json()
          setChats(prev => [data, ...prev])
          const newId = data.id || data.uuid
          currentChatId = newId
          setActiveChatId(newId)
          // Update URL without triggering Next.js page re-mount
          window.history.replaceState(null, '', `/chat/${newId}`)
          // Don't return, continue sending message
        }
      } catch (e) { console.error(e); return }
    }

    const userMessage = {
      role: "user",
      content: contentToSend,
      id: Date.now(),
      provider,
      // Local previews for immediate display
      _imageUrls: imagesToUpload.map(f => URL.createObjectURL(f)),
      _pdfNames: pdfsToUpload.map(f => f.name),
    }
    setMessages(prev => [...prev, userMessage])
    setIsTyping(true)

    const formData = new FormData()
    formData.append("content", contentToSend)
    formData.append("provider", provider)
    imagesToUpload.forEach(f => formData.append("images[]", f))
    pdfsToUpload.forEach(f => formData.append("files[]", f))

    if (isTemporary) {
      // In temporary mode, we must send ALL messages as context
      const allMessages = [...messages, userMessage].map(m => ({
        role: m.role,
        content: m.content
      }))
      formData.append("messages", JSON.stringify(allMessages))
    }

    try {
      const url = isTemporary 
        ? `${API_BASE}/chats/temporary/messages` 
        : `${API_BASE}/chats/${currentChatId}/messages`
        
      const res = await apiFetch(url, {
        method: "POST",
        headers: { Accept: "text/event-stream" },
        body: formData
      })

      if (!res.ok) throw new Error('Failed to fetch')

      const assistantMsgId = Date.now() + 1
      setMessages(prev => [...prev, { role: "assistant", content: "", id: assistantMsgId, provider }])
      // Keep isTyping=true until the first token arrives so the spinner stays visible
      setStreamingMsgId(assistantMsgId)

      const reader = res.body?.getReader()
      const decoder = new TextDecoder()
      let done = false, buffer = "", currentContent = "", lastUpdate = Date.now()
      let actualProvider = provider
      let streamingStarted = false  // tracks first SSE event (even empty ones)

      while (reader && !done) {
        const { value, done: doneReading } = await reader.read()
        done = doneReading
        if (value) {
          buffer += decoder.decode(value, { stream: true })
          const lines = buffer.split('\n')
          buffer = lines.pop() || ''

          let newTokens = ""
          for (const line of lines) {
            if (line.startsWith('data: ')) {
              const dataStr = line.substring(6).trim()
              if (dataStr === '[DONE]') break
              try {
                const data = JSON.parse(dataStr)
                if (data.error) throw new Error(data.error)
                // Dismiss spinner on very first valid SSE event, even if text is empty
                // (Ollama sends many {"text":""} events before real content)
                if (!streamingStarted) {
                  streamingStarted = true
                  setIsTyping(false)
                }
                if (data.text) newTokens += data.text
                if (data.meta?.provider) {
                  actualProvider = data.meta.provider
                  setMessages(prev => prev.map(m => m.id === assistantMsgId ? { ...m, provider: actualProvider } : m))
                }
              } catch (_) {}
            }
          }
          if (newTokens) {
            currentContent += newTokens
            if (Date.now() - lastUpdate > 50) {
              setMessages(prev => prev.map(m => m.id === assistantMsgId ? { ...m, content: currentContent } : m))
              lastUpdate = Date.now()
            }
          }
        }
      }

      // Always clear spinner at end of stream (handles edge cases)
      setIsTyping(false)
      setMessages(prev => prev.map(m => m.id === assistantMsgId ? { ...m, content: currentContent, provider: actualProvider } : m))
      setStreamingMsgId(null)
    } catch (e) {
      console.error(e)
      setMessages(prev => [...prev, { role: "assistant", content: "⚠️ Connection error. Please try again.", id: Date.now(), provider }])
      setIsTyping(false)
      setStreamingMsgId(null)
    }
  }

  const handleRetry = (idx: number) => {
    const prev = messages.slice(0, idx).reverse().find(m => m.role === 'user')
    if (prev) handleSend(prev.content)
  }

  // Inline markdown code renderer
  const CodeBlock = ({ inline, className, children, ...props }: any) => {
    const match = /language-(\w+)/.exec(className || '')
    return !inline && match ? (
      <div className="rounded-lg overflow-hidden border my-6 bg-[#1e1e1e] shadow-sm">
        <div className="flex items-center justify-between px-4 py-2 bg-[#2d2d2d] text-xs text-slate-300 font-mono">
          <span>{match[1]}</span>
          <CopyButton text={String(children).replace(/\n$/, '')} />
        </div>
        <SyntaxHighlighter {...props} style={vscDarkPlus} language={match[1]} PreTag="div" customStyle={{ margin: 0, padding: '1rem', background: 'transparent' }}>
          {String(children).replace(/\n$/, '')}
        </SyntaxHighlighter>
      </div>
    ) : (
      <code {...props} className={`${className} bg-muted/60 text-foreground px-1.5 py-0.5 rounded-md font-mono text-xs before:content-[''] after:content-['']`}>
        {children}
      </code>
    )
  }

  return (
    <SidebarProvider>
      <AppSidebar 
        chats={chats} 
        fetchChats={fetchChats} 
        createChat={createChat} 
        loadChat={handleSidebarChatClick} 
        deleteChat={deleteChat} 
        activeChatId={activeChatId} 
        user={user}
        onLogout={handleLogout}
      />
      <SidebarInset className="flex flex-col h-screen bg-background text-foreground">
        {/* Header */}
        <header className="flex h-14 items-center justify-between shrink-0 px-6 gap-2 border-b border-border/40">
          <div className="flex items-center gap-2">
            <SidebarTrigger />
            <h1 className="font-semibold text-sm tracking-wide">Nexus AI</h1>
          </div>
          <div className="flex items-center gap-4">
            {!user ? (
              <div className="flex items-center gap-3 mr-2">
                <Link href="/login" className="text-sm font-medium hover:underline text-muted-foreground">Log in</Link>
                <Link href="/register" className="text-sm font-medium bg-primary text-primary-foreground px-3 py-1.5 rounded-md hover:bg-primary/90 transition-colors">Sign up</Link>
              </div>
            ) : (
              <div className="flex items-center gap-2 mr-2">
                <span className="text-xs font-medium text-muted-foreground">Temp Chat</span>
                <Switch 
                  checked={isTemporary} 
                  onCheckedChange={(checked) => {
                    setIsTemporary(checked)
                    if (checked) {
                      // Reset to fresh temp chat state without page navigation
                      setActiveChatId(null)
                      setMessages([])
                      window.history.replaceState(null, '', '/')
                    }
                  }} 
                />
              </div>
            )}
            <Select value={provider} onValueChange={(v) => v && setProvider(v)}>
              <SelectTrigger className="w-[180px] h-8 text-xs bg-muted/30 border-none shadow-none">
                <SelectValue placeholder="Select AI" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="ollama">Ollama (Deepseek v3.2)</SelectItem>
                <SelectItem value="openai">OpenAI (GPT-3.5)</SelectItem>
                <SelectItem value="claude">Claude (3.5 Sonnet)</SelectItem>
                <SelectItem value="gemini">Gemini (1.5 Pro)</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </header>

        {/* Messages */}
        <ScrollArea className="flex-1 px-4 w-full">
          <div className="flex flex-col gap-8 py-6 w-full max-w-3xl mx-auto pb-8">
            {messages.length === 0 && (
              <div className="flex flex-col items-center justify-center pt-32 text-center opacity-60">
                <Bot className="w-12 h-12 mb-6" />
                <h2 className="text-2xl font-semibold tracking-tight">How can I help you today?</h2>
                <p className="text-sm mt-2 text-muted-foreground">Ask a question, share code, or upload a PDF or image.</p>
              </div>
            )}

            {messages.map((msg, idx) => (
              <div key={msg.id ?? idx} className={`flex gap-4 w-full ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                {msg.role === 'assistant' && (
                  <Avatar className="w-8 h-8 shrink-0 mt-1 border shadow-sm">
                    <AvatarFallback className="bg-background text-primary"><Bot size={18} /></AvatarFallback>
                  </Avatar>
                )}

                <div className={`flex flex-col max-w-[90%] sm:max-w-[85%] ${msg.role === 'user' ? 'items-end' : 'items-start min-w-0'}`}>
                  {msg.role === 'user' ? (
                    <div className="flex flex-col items-end gap-2">
                      {/* Image previews */}
                      {(msg._imageUrls?.length > 0 || msg.attachments?.some((a: any) => (a.type ?? 'image') === 'image')) && (
                        <div className="flex flex-wrap gap-2 justify-end">
                          {(msg._imageUrls || []).map((url: string, i: number) => (
                            <img key={'lu-' + i} src={url} alt="img" className="w-40 rounded-xl border-2 border-muted shadow-sm object-cover" />
                          ))}
                          {(msg.attachments || []).filter((a: any) => (a.type ?? 'image') === 'image').map((a: any, i: number) => (
                            <img key={'la-' + i} src={a.path.replace('public/', `${STORAGE_BASE}/`)} alt="img" className="w-40 rounded-xl border-2 border-muted shadow-sm object-cover" />
                          ))}
                        </div>
                      )}
                      {/* PDF chips */}
                      {(msg._pdfNames?.length > 0 || msg.attachments?.some((a: any) => a.type === 'pdf')) && (
                        <div className="flex flex-wrap gap-2 justify-end">
                          {(msg._pdfNames || []).map((name: string, i: number) => (
                            <div key={'pn-' + i} className="flex items-center gap-1.5 bg-muted border rounded-xl px-3 py-1.5 text-xs font-medium">
                              <FileText className="w-3.5 h-3.5 text-red-400 shrink-0" />{name}
                            </div>
                          ))}
                          {(msg.attachments || []).filter((a: any) => a.type === 'pdf').map((a: any, i: number) => (
                            <div key={'pa-' + i} className="flex items-center gap-1.5 bg-muted border rounded-xl px-3 py-1.5 text-xs font-medium">
                              <FileText className="w-3.5 h-3.5 text-red-400 shrink-0" />{a.name}
                            </div>
                          ))}
                        </div>
                      )}
                      {msg.content && (
                        <div className="bg-muted px-5 py-3 rounded-3xl rounded-tr-sm text-[15px] shadow-sm whitespace-pre-wrap">
                          {msg.content}
                        </div>
                      )}
                      {/* User provider badge */}
                      {msg.provider && (
                        <span className={`text-[11px] ${PROVIDER_COLORS[msg.provider] ?? 'text-muted-foreground'} opacity-70`}>
                          sent to {PROVIDER_LABELS[msg.provider] ?? msg.provider}
                        </span>
                      )}
                    </div>
                  ) : (
                    <div className="w-full min-w-0 px-2 py-1">
                      <div className="prose prose-sm dark:prose-invert max-w-none text-foreground prose-p:leading-7 prose-pre:p-0 prose-pre:bg-transparent">
                        {msg.content ? (
                          <ReactMarkdown remarkPlugins={[remarkGfm]} components={{ code: CodeBlock }}>
                            {msg.content}
                          </ReactMarkdown>
                        ) : streamingMsgId === msg.id ? (
                          // Empty content but streaming — show a waiting pulse
                          <span className="inline-flex items-center gap-1.5 text-muted-foreground text-sm">
                            <span className="w-1.5 h-1.5 rounded-full bg-current animate-bounce [animation-delay:0ms]" />
                            <span className="w-1.5 h-1.5 rounded-full bg-current animate-bounce [animation-delay:150ms]" />
                            <span className="w-1.5 h-1.5 rounded-full bg-current animate-bounce [animation-delay:300ms]" />
                          </span>
                        ) : null}
                        {/* Blinking cursor while streaming */}
                        {streamingMsgId === msg.id && msg.content && (
                          <span className="inline-block w-0.5 h-4 bg-current align-middle ml-0.5 animate-pulse" />
                        )}
                      </div>

                      {/* Action bar: only after streaming done */}
                      {streamingMsgId !== msg.id && (
                        <div className="flex items-center gap-4 mt-3 pl-1 flex-wrap">
                          {/* Provider badge */}
                          {msg.provider && (
                            <span className={`text-[11px] font-medium ${PROVIDER_COLORS[msg.provider] ?? 'text-muted-foreground'} flex items-center gap-1`}>
                              <span className="w-1.5 h-1.5 rounded-full bg-current opacity-80 inline-block" />
                              {PROVIDER_LABELS[msg.provider] ?? msg.provider}
                            </span>
                          )}
                          <CopyButton text={msg.content} />
                          <button onClick={() => handleRetry(idx)} className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors">
                            <RefreshCw className="w-3 h-3" /> Retry
                          </button>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            ))}

            {isTyping && (
              <div className="flex gap-4 justify-start w-full">
                <Avatar className="w-8 h-8 shrink-0 mt-1 border shadow-sm">
                  <AvatarFallback className="bg-background text-primary"><Bot size={18} /></AvatarFallback>
                </Avatar>
                <div className="flex items-center space-x-2 py-2 px-2">
                  <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />
                  <span className="text-xs text-muted-foreground">Thinking...</span>
                </div>
              </div>
            )}
            <div ref={scrollRef} />
          </div>
        </ScrollArea>

        <ChatInput isTyping={isTyping} onSend={handleSend} />
      </SidebarInset>
    </SidebarProvider>
  )
}
