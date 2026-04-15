"use client"

import { useState, useEffect, useRef } from "react"
import { Send, User, Bot, Loader2 } from "lucide-react"
import { SidebarProvider, SidebarInset, SidebarTrigger } from "@/components/ui/sidebar"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { AppSidebar } from "@/components/app-sidebar"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"

const API_BASE = "http://localhost:8000/api"

export default function Home() {
  const [chats, setChats] = useState<any[]>([])
  const [activeChatId, setActiveChatId] = useState<string | null>(null)
  const [messages, setMessages] = useState<any[]>([])
  const [input, setInput] = useState("")
  const [isTyping, setIsTyping] = useState(false)
  const [provider, setProvider] = useState("openai")
  const scrollRef = useRef<HTMLDivElement>(null)

  // Fetch past chats
  const fetchChats = async () => {
    try {
      const res = await fetch(`${API_BASE}/chats`, {
        headers: { "Accept": "application/json" }
      })
      const data = await res.json()
      setChats(data)
    } catch(e) {
      console.error("Failed to fetch chats", e)
    }
  }

  // Effect to load on mount
  useEffect(() => {
    fetchChats()
  }, [])

  // Auto scroll to bottom
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollIntoView({ behavior: "smooth" })
    }
  }, [messages, isTyping])

  const createChat = async () => {
    try {
      const res = await fetch(`${API_BASE}/chats`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ title: "New Session" })
      })
      const data = await res.json()
      setChats([data, ...chats])
      setActiveChatId(data.id)
      setMessages([])
    } catch(e) { console.error(e) }
  }

  const loadChat = async (id: string) => {
    try {
      setActiveChatId(id)
      const res = await fetch(`${API_BASE}/chats/${id}`, {
        headers: { "Accept": "application/json" }
      })
      const data = await res.json()
      setMessages(data.messages || [])
    } catch(e) { console.error(e) }
  }

  const sendMessage = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!input.trim() || isTyping) return

    let currentChatId = activeChatId
    if (!currentChatId) {
      // Auto create a chat if none is active
      try {
        const res = await fetch(`${API_BASE}/chats`, {
          method: "POST",
          headers: { "Content-Type": "application/json", "Accept": "application/json" },
          body: JSON.stringify({ title: input.substring(0, 30) + '...' })
        })
        const data = await res.json()
        setChats([data, ...chats])
        currentChatId = data.id
        setActiveChatId(data.id)
      } catch(e) { console.error(e) }
    }

    if (!currentChatId) return

    const userMessage = { role: "user", content: input, id: Date.now() }
    setMessages(prev => [...prev, userMessage])
    setInput("")
    setIsTyping(true)

    try {
      const res = await fetch(`${API_BASE}/chats/${currentChatId}/messages`, {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json" },
        body: JSON.stringify({ content: userMessage.content, provider })
      })
      const aiReply = await res.json()
      
      if (!res.ok) throw new Error(aiReply.error || 'Failed to fetch')
      
      setMessages(prev => [...prev, aiReply])
    } catch(e) {
      console.error(e)
      setMessages(prev => [...prev, { role: "assistant", content: "Error connecting to AI.", id: Date.now() }])
    } finally {
      setIsTyping(false)
    }
  }

  return (
    <SidebarProvider>
      <AppSidebar chats={chats} fetchChats={fetchChats} createChat={createChat} loadChat={loadChat} />
      <SidebarInset className="flex flex-col h-screen bg-background text-foreground">
        <header className="flex h-14 items-center justify-between shrink-0 border-b px-4 gap-2">
          <div className="flex items-center gap-2">
            <SidebarTrigger />
            <h1 className="font-semibold text-sm">AG AI Session</h1>
          </div>
          <Select value={provider} onValueChange={setProvider}>
            <SelectTrigger className="w-[180px] h-8 text-xs">
              <SelectValue placeholder="Select AI" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="openai">OpenAI (GPT-3.5)</SelectItem>
              <SelectItem value="claude">Claude (3.5 Sonnet)</SelectItem>
              <SelectItem value="gemini">Gemini (1.5 Pro)</SelectItem>
            </SelectContent>
          </Select>
        </header>

        <ScrollArea className="flex-1 p-4 w-full md:max-w-4xl mx-auto flex flex-col justify-end">
          <div className="flex flex-col gap-6 py-4">
            {messages.length === 0 ? (
              <div className="h-full flex flex-col items-center justify-center pt-24 text-center opacity-50">
                <Bot className="w-12 h-12 mb-4" />
                <h2 className="text-xl font-semibold">How can I help you today?</h2>
              </div>
            ) : null}

            {messages.map((msg, idx) => (
              <div key={idx} className={`flex gap-4 ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                {msg.role === 'assistant' && (
                  <Avatar className="w-8 h-8 shrink-0">
                    <AvatarFallback className="bg-primary text-primary-foreground"><Bot size={16} /></AvatarFallback>
                  </Avatar>
                )}
                
                <div className={`p-4 rounded-xl max-w-[80%] ${msg.role === 'user' ? 'bg-primary text-primary-foreground ml-12' : 'bg-muted mr-12'}`}>
                  <p className="whitespace-pre-wrap flex-1 text-sm">{msg.content}</p>
                </div>

                {msg.role === 'user' && (
                   <Avatar className="w-8 h-8 shrink-0">
                      <AvatarFallback><User size={16} /></AvatarFallback>
                   </Avatar>
                )}
              </div>
            ))}
            {isTyping && (
              <div className="flex gap-4 justify-start">
                  <Avatar className="w-8 h-8 shrink-0">
                    <AvatarFallback className="bg-primary text-primary-foreground"><Bot size={16} /></AvatarFallback>
                  </Avatar>
                  <div className="p-4 rounded-xl max-w-[80%] bg-muted flex items-center space-x-2 mr-12">
                     <Loader2 className="h-4 w-4 animate-spin opacity-50" />
                     <span className="text-sm opacity-50">Thinking...</span>
                  </div>
              </div>
            )}
            <div ref={scrollRef} />
          </div>
        </ScrollArea>

        <div className="p-4 bg-background border-t">
          <form onSubmit={sendMessage} className="flex gap-2 max-w-4xl mx-auto w-full relative">
            <Input 
              value={input} 
              onChange={e => setInput(e.target.value)} 
              placeholder="Message AG AI..."
              className="pr-12 py-6 rounded-full shadow-sm"
              disabled={isTyping}
            />
            <Button disabled={isTyping || !input.trim()} type="submit" size="icon" className="absolute right-1 top-1 bottom-1 h-auto rounded-full">
              <Send className="h-4 w-4" />
            </Button>
          </form>
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
