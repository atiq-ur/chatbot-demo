"use client"

import { use } from "react"
import ChatInterface from "@/components/chat-interface"
import { AuthGuard } from "@/components/auth-guard"

export default function ChatPage({ params }: { params: Promise<{ uuid: string }> }) {
  const { uuid } = use(params)
  
  return (
    <AuthGuard>
      <ChatInterface initialChatId={uuid} />
    </AuthGuard>
  )
}
