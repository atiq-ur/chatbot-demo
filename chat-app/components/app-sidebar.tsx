import { Calendar, Home, Inbox, Search, Settings, MessageSquarePlus, RefreshCw, MessageCircle } from "lucide-react"

import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"

// Note: In a real app we'd fetch this from the backend
export function AppSidebar({ chats, fetchChats, createChat, loadChat }: any) {
  return (
    <Sidebar>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Ag Ai Assistant</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton asChild onClick={createChat}>
                   <a href="#" className="font-semibold text-primary">
                    <MessageSquarePlus className="mr-2 h-4 w-4" />
                    <span>New Chat</span>
                  </a>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
        
        <SidebarGroup>
          <div className="flex justify-between items-center pr-4">
              <SidebarGroupLabel>Previous Chats</SidebarGroupLabel>
              <button onClick={fetchChats} className="text-muted-foreground hover:text-primary"><RefreshCw className="h-3 w-3" /></button>
          </div>
          <SidebarGroupContent>
            <SidebarMenu>
              {chats.map((chat: any) => (
                <SidebarMenuItem key={chat.id}>
                  <SidebarMenuButton asChild onClick={() => loadChat(chat.id)}>
                    <a href="#">
                      <MessageCircle className="h-4 w-4" />
                      <span>{chat.title || 'New Session'}</span>
                    </a>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
              {chats.length === 0 && (
                <SidebarMenuItem>
                  <span className="text-xs text-muted-foreground pl-3">No chats yet. Start one!</span>
                </SidebarMenuItem>
              )}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
    </Sidebar>
  )
}
